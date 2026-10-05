<?php

namespace App\Http\Controllers;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyStatus;
use App\Enums\DocumentStatus;
use App\Enums\SihandalMatchStatus;
use App\Enums\SihandalSyncStatus;
use App\Models\AiUsage;
use App\Models\Document;
use App\Models\DocumentAnomaly;
use App\Models\DocumentExtractionField;
use App\Models\SihandalMatch;
use App\Models\SihandalSync;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reports & financial analysis (spec §26, §32, §56):
 * platform-wide for super admins, OPD-scoped for everyone
 * else (§36). Every number is computed from real pipeline
 * state — documents, extraction fields, anomalies, SIHANDAL
 * syncs and AI usage — never from cached approximations.
 */
final class ReportsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        \assert($user instanceof User);

        $isPlatform = $user->isSuperAdmin();

        // Document-derived metrics: OPD-scoped (§36).
        $documents = Document::query()->accessibleBy($user);

        $statusCounts = (clone $documents)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $byFiscalYear = (clone $documents)
            ->select('fiscal_year', DB::raw('count(*) as total'))
            ->whereNotNull('fiscal_year')
            ->groupBy('fiscal_year')
            ->orderBy('fiscal_year')
            ->pluck('total', 'fiscal_year');

        $byType = (clone $documents)
            ->join('document_types', 'document_types.id', '=', 'documents.document_type_id')
            ->select('document_types.code', DB::raw('count(*) as total'))
            ->groupBy('document_types.code')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'document_types.code');

        // Extraction confidence distribution (§12): the field
        // rows of the OPD's completed extractions, in buckets.
        $fields = DocumentExtractionField::query()
            ->whereHas('extraction.document', fn (Builder $q): Builder => $q->accessibleBy($user))
            ->whereNotNull('confidence');

        $confidence = [
            'high' => (clone $fields)->where('confidence', '>=', 85)->count(),
            'medium' => (clone $fields)->whereBetween('confidence', [60, 84])->count(),
            'low' => (clone $fields)->where('confidence', '<', 60)->count(),
        ];

        // Review workload: what is waiting for a human right now.
        $anomalies = DocumentAnomaly::query()
            ->whereHas('document', fn (Builder $q): Builder => $q->accessibleBy($user))
            ->open();

        $workload = [
            'review_required' => (clone $documents)->where('status', DocumentStatus::ReviewRequired)->count(),
            'open_anomalies' => (clone $anomalies)->count(),
            'critical_anomalies' => (clone $anomalies)->where('severity', AnomalySeverity::Critical->value)->count(),
            'high_anomalies' => (clone $anomalies)->where('severity', AnomalySeverity::High->value)->count(),
            'resolved_anomalies' => DocumentAnomaly::query()
                ->whereHas('document', fn (Builder $q): Builder => $q->accessibleBy($user))
                ->where('status', AnomalyStatus::Resolved->value)
                ->count(),
            'pending_sihandal_confirmations' => SihandalMatch::query()
                ->where('status', SihandalMatchStatus::Pending->value)
                ->whereHas('document', fn (Builder $q): Builder => $q->accessibleBy($user))
                ->count(),
        ];

        // SIHANDAL sync health (§48, §50).
        $syncs = SihandalSync::query()
            ->whereHas('document', fn (Builder $q): Builder => $q->accessibleBy($user));

        $syncHealth = [
            'pending' => (clone $syncs)->where('status', SihandalSyncStatus::Pending->value)->count(),
            'success' => (clone $syncs)->where('status', SihandalSyncStatus::Success->value)->count(),
            'failed' => (clone $syncs)->where('status', SihandalSyncStatus::Failed->value)->count(),
        ];

        // AI token/cost dashboard (§32, §56). Chat runs carry no
        // document, so OPD users see the usage of their own
        // documents' runs; platform staff see everything.
        $usage = AiUsage::query()
            ->whereHas('run.document', fn (Builder $q): Builder => $q->accessibleBy($user));

        $cost = [
            'calls' => (clone $usage)->count(),
            'input_tokens' => (int) (clone $usage)->sum('input_tokens'),
            'output_tokens' => (int) (clone $usage)->sum('output_tokens'),
            // Fixed 8-decimal formatting so the dashboard reads
            // identically on SQLite (tests) and MySQL (prod).
            'cost_estimate' => number_format(
                (float) (clone $usage)->sum('cost_estimate'),
                8,
                '.',
                '',
            ),
            'avg_duration_ms' => (int) (clone $usage)->avg('duration_ms'),
        ];

        $usageByModel = (clone $usage)
            ->get(['model', 'input_tokens', 'output_tokens', 'cost_estimate'])
            ->groupBy('model')
            ->map(fn ($rows) => [
                'model' => $rows->first()->model,
                'calls' => $rows->count(),
                'input_tokens' => (int) $rows->sum('input_tokens'),
                'output_tokens' => (int) $rows->sum('output_tokens'),
                'cost_estimate' => number_format(
                    (float) $rows->sum('cost_estimate'),
                    8,
                    '.',
                    '',
                ),
            ])
            ->sortByDesc('calls')
            ->values()
            ->all();

        return Inertia::render('reports/index', [
            'scope' => $isPlatform ? 'platform' : 'opd',
            'documents' => [
                'total' => (int) $statusCounts->sum(),
                'by_status' => $statusCounts->map(fn ($total) => (int) $total)->all(),
                'by_fiscal_year' => $byFiscalYear->map(fn ($total) => (int) $total)->all(),
                'by_type' => $byType->map(fn ($total) => (int) $total)->all(),
            ],
            'confidence' => $confidence,
            'workload' => $workload,
            'sync_health' => $syncHealth,
            'ai_cost' => $cost,
            'ai_cost_by_model' => $usageByModel,
        ]);
    }
}
