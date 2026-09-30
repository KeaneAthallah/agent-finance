<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\Validation\ValidationEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Deterministic validation stage (spec §5, §15): runs the rule engine over
 * the completed extraction and routes the document to review_required or
 * approved. Cheap and failure-safe: validation never blocks extraction
 * results, which are already persisted.
 */
final class ValidateDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int
     */
    public $tries = 2;

    /**
     * @var int
     */
    public $backoff = 30;

    /**
     * @var int
     */
    public $timeout = 120;

    public function __construct(
        public readonly int $documentId,
        public readonly int $version,
    ) {}

    public function handle(ValidationEngine $engine): void
    {
        $document = Document::query()->findOrFail($this->documentId);

        // Stale-retry guard (spec §31), consistent with the other stages.
        if ((int) $document->processingRuns()->max('version') !== $this->version) {
            return;
        }

        $run = $document->processingRuns()->create([
            'version' => $this->version,
            'stage' => 'validation',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $document->update(['status' => DocumentStatus::Validation]);

            $engine->validate($document);

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'duration_ms' => (int) ($run->started_at?->diffInMilliseconds(now()) ?? 0),
            ]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'finished_at' => now(),
                'duration_ms' => (int) ($run->started_at?->diffInMilliseconds(now()) ?? 0),
            ]);

            $document->update(['status' => DocumentStatus::ValidationFailed]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $document = Document::query()->find($this->documentId);

        if ($document !== null) {
            $document->update(['status' => DocumentStatus::ValidationFailed]);
        }
    }
}
