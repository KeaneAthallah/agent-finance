<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Operational dashboard (spec §26): counters over real pipeline state,
 * OPD-scoped (§36). Charts deliberately minimal — a work surface, not a
 * showcase.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        \assert($user instanceof User);

        $base = Document::query()->accessibleBy($user);

        $counts = (clone $base)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $count = fn (DocumentStatus ...$statuses) => collect($statuses)
            ->sum(fn (DocumentStatus $s) => (int) $counts->get($s->value, 0));

        return Inertia::render('dashboard', [
            'counters' => [
                'uploaded' => $count(DocumentStatus::Uploaded),
                'processing' => $count(
                    DocumentStatus::Queued,
                    DocumentStatus::Processing,
                    DocumentStatus::Ocr,
                    DocumentStatus::AiAnalysis,
                ),
                'needs_review' => $count(DocumentStatus::ReviewRequired),
                'approved' => $count(DocumentStatus::Approved),
                'synced' => $count(DocumentStatus::Synced, DocumentStatus::Completed),
                'failed' => $count(
                    DocumentStatus::ProcessingFailed,
                    DocumentStatus::OcrFailed,
                    DocumentStatus::AiFailed,
                    DocumentStatus::ValidationFailed,
                    DocumentStatus::SyncFailed,
                ),
            ],
            'user' => [
                'name' => $user->name,
                'role' => $user->role?->name,
                'opd' => $user->opd?->name,
            ],
        ]);
    }
}
