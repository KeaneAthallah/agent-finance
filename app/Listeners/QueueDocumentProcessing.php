<?php

namespace App\Listeners;

use App\Actions\StartDocumentProcessingAction;
use App\Events\DocumentUploaded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Auto-starts the processing pipeline after a successful upload (spec §30).
 * Queued so the HTTP response never waits on pipeline startup. The upload
 * itself already succeeded — a pipeline-start failure is logged, never
 * allowed to break the upload response.
 */
final class QueueDocumentProcessing implements ShouldQueue
{
    public function __construct(
        private readonly StartDocumentProcessingAction $action,
    ) {}

    public function handle(DocumentUploaded $event): void
    {
        try {
            $this->action->execute($event->document);
        } catch (Throwable $exception) {
            Log::error('Failed to start document processing', [
                'document_id' => $event->document->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
