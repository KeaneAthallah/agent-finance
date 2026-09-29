<?php

namespace App\Actions;

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use Illuminate\Support\Facades\DB;

/**
 * Starts (or restarts) the processing pipeline for a document (spec §30, §31):
 * increments the processing version, records the run, flips status to queued
 * and dispatches the head job. Retries reuse this action — no re-upload.
 */
final class StartDocumentProcessingAction
{
    public function execute(Document $document): Document
    {
        return DB::transaction(function () use ($document): Document {
            $latestVersion = (int) $document->processingRuns()
                ->max('version');

            $document->processingRuns()->create([
                'version' => $latestVersion + 1,
                'stage' => 'pipeline',
                'status' => 'pending',
            ]);

            $document->update(['status' => DocumentStatus::Queued]);

            ProcessDocumentJob::dispatch($document->id, $latestVersion + 1);

            return $document->refresh();
        });
    }
}
