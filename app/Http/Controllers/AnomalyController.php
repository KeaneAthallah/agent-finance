<?php

namespace App\Http\Controllers;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyStatus;
use App\Enums\AnomalyType;
use App\Models\Document;
use App\Models\DocumentAnomaly;
use App\Models\User;
use App\Support\ActorType;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Anomaly workspace (spec §15, §25, §36): the cross-document
 * queue of open anomaly indicators with their severity, the
 * document context and the SIHANDAL comparison, plus the
 * human resolve/dismiss lifecycle. Anomalies are factual
 * indicators — resolving one records who decided and when;
 * it never claims fraud on the system's say-so.
 */
final class AnomalyController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        \assert($user instanceof User);

        $severity = $request->validate(['severity' => ['nullable', 'string', 'in:'.implode(',', AnomalySeverity::values())]]);
        $type = $request->validate(['type' => ['nullable', 'string', 'in:'.implode(',', AnomalyType::values())]]);

        $anomalies = DocumentAnomaly::query()
            ->whereHas('document', fn (Builder $q): Builder => $q->accessibleBy($user))
            ->open()
            ->when($severity['severity'] ?? null, fn (Builder $q): Builder => $q->where('severity', $severity['severity']))
            ->when($type['type'] ?? null, fn (Builder $q): Builder => $q->where('type', $type['type']))
            ->with([
                'document:id,document_number,original_filename,fiscal_year,status,opd_id,document_type_id,created_at',
                'document.documentType:id,code,name',
                'document.opd:id,code,name',
            ])
            // Worst severity first (portable CASE ordering), then newest.
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('anomalies/index', [
            'anomalies' => $anomalies,
            'filters' => [
                'severity' => $severity['severity'] ?? null,
                'type' => $type['type'] ?? null,
            ],
            'severities' => AnomalySeverity::values(),
            'types' => AnomalyType::values(),
            'can' => [
                'review' => $user->can('review', Document::class),
            ],
        ]);
    }

    public function resolve(Request $request, DocumentAnomaly $anomaly): RedirectResponse|JsonResponse
    {
        return $this->decide($request, $anomaly, AnomalyStatus::Resolved, 'anomaly.resolved');
    }

    public function dismiss(Request $request, DocumentAnomaly $anomaly): RedirectResponse|JsonResponse
    {
        return $this->decide($request, $anomaly, AnomalyStatus::Dismissed, 'anomaly.dismissed');
    }

    /**
     * Human decision on an anomaly indicator (spec §15, §25, §35):
     * only reviewers within the anomaly's OPD may decide; the
     * decision and its actor are recorded on the anomaly and in
     * the audit trail.
     */
    private function decide(Request $request, DocumentAnomaly $anomaly, AnomalyStatus $status, string $action): RedirectResponse|JsonResponse
    {
        $this->authorize('review', $anomaly->document);

        $user = $request->user();

        \assert($user instanceof User);

        $anomaly->update([
            'status' => $status,
            'resolved_by' => $user->id,
            'resolved_at' => now(),
        ]);

        AuditLogger::record(
            action: $action,
            subject: $anomaly,
            actor: $user,
            actorType: ActorType::Human,
            request: $request,
            properties: [
                'anomaly_type' => $anomaly->type->value,
                'severity' => $anomaly->severity->value,
                'document_id' => $anomaly->document_id,
                'decision' => $status->value,
            ],
        );

        if ($request->expectsJson()) {
            return response()->json([
                'resolved' => true,
                'status' => $anomaly->refresh()->status->value,
            ]);
        }

        return redirect()
            ->route('anomalies.index')
            ->with('success', 'Anomaly marked as '.$status->value.'.');
    }
}
