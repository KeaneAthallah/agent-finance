<?php

namespace App\Actions;

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\User;
use App\Support\ActorType;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Executes a human review decision (spec §14): approve, reject, or request
 * re-analysis. This is the write path where "humans decide" becomes state:
 * every decision records who decided, on which extraction version, with the
 * payload snapshot and an audit entry (actor: human).
 */
final class ReviewDocumentAction
{
    /**
     * @param  array<string, mixed>|null  $extractionSnapshot
     */
    public function execute(
        Document $document,
        User $reviewer,
        string $decision,
        ?string $notes,
        ?int $extractionVersion = null,
        ?array $extractionSnapshot = null,
    ): DocumentReview {
        // Snapshot the extraction the reviewer decided on (spec §38): defaults
        // to the latest completed pass so history survives re-processing.
        if ($extractionVersion === null) {
            $extraction = $document->extractions()
                ->where('status', 'completed')
                ->orderByDesc('version')
                ->first();

            $extractionVersion = $extraction?->version;
            $extractionSnapshot = $extraction?->payload;
        }

        $review = DB::transaction(function () use ($document, $reviewer, $decision, $notes, $extractionVersion, $extractionSnapshot): DocumentReview {
            $review = $document->reviews()->create([
                'reviewer_id' => $reviewer->id,
                'decision' => $decision,
                'notes' => $notes,
                'extraction_version' => $extractionVersion,
                'extraction_snapshot' => $extractionSnapshot,
            ]);

            match ($decision) {
                'approved' => $document->update(['status' => DocumentStatus::Approved]),
                'rejected' => $document->update(['status' => DocumentStatus::Rejected]),
                'reanalysis_requested' => $this->restartPipeline($document),
                default => throw new \InvalidArgumentException("Unknown review decision [{$decision}]."),
            };

            return $review;
        });

        AuditLogger::record(
            action: 'document.reviewed',
            subject: $document,
            actor: $reviewer,
            actorType: ActorType::Human,
            properties: [
                'decision' => $decision,
                'notes' => $notes,
                'extraction_version' => $extractionVersion,
                'review_id' => $review->id,
                'new_status' => $document->refresh()->status->value,
            ],
        );

        return $review;
    }

    /**
     * Re-analysis (spec §14): restarts the full pipeline under a new
     * processing version — extraction history is kept (§38).
     */
    private function restartPipeline(Document $document): void
    {
        $latestVersion = (int) $document->processingRuns()->max('version');

        $document->processingRuns()->create([
            'version' => $latestVersion + 1,
            'stage' => 'pipeline',
            'status' => 'pending',
        ]);

        $document->update(['status' => DocumentStatus::Queued]);

        ProcessDocumentJob::dispatch($document->id, $latestVersion + 1);
    }
}
