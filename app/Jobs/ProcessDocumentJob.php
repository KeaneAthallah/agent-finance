<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentProcessingRun;
use App\Services\Extraction\TextExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Pipeline head (spec §30): prepares pages, then runs text extraction and
 * classification sequentially on the queue. Failures set explicit failure
 * states — never silent (spec §5, §31).
 */
final class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int
     */
    public $tries = 3;

    /**
     * @var int
     */
    public $backoff = 30;

    /**
     * @var int
     */
    public $timeout = 900;

    public function __construct(
        public readonly int $documentId,
        public readonly int $version,
    ) {}

    public function handle(TextExtractor $extractor): void
    {
        $document = Document::query()->findOrFail($this->documentId);

        // Guard against stale retries of an older processing version.
        $latestVersion = (int) $document->processingRuns()->max('version');

        if ($latestVersion !== $this->version) {
            return;
        }

        $run = $this->beginStage($document, 'text_extraction');

        try {
            $document->update(['status' => DocumentStatus::Processing]);

            $pages = $extractor->extract(
                disk: (string) config('agent-finance.documents.disk'),
                path: $document->file_path,
                mimeType: $document->mime_type,
            );

            foreach ($pages as $number => $text) {
                $document->pages()->updateOrCreate(
                    ['page_number' => $number],
                    [
                        'extracted_text' => $text,
                        'text_source' => 'embedded',
                        'processing_status' => 'done',
                        'error' => null,
                    ],
                );
            }

            $document->update([
                'page_count' => count($pages),
                'status' => DocumentStatus::Ocr,
            ]);

            $this->completeStage($run);

            ExtractTextJob::dispatch($document->id, $this->version);
        } catch (Throwable $exception) {
            $this->failStage($run, $exception);

            $document->update(['status' => DocumentStatus::ProcessingFailed]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $document = Document::query()->find($this->documentId);

        if ($document !== null) {
            $document->update(['status' => DocumentStatus::ProcessingFailed]);
        }
    }

    private function beginStage(Document $document, string $stage): DocumentProcessingRun
    {
        return $document->processingRuns()->create([
            'version' => $this->version,
            'stage' => $stage,
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    private function completeStage(DocumentProcessingRun $run): void
    {
        $run->update([
            'status' => 'completed',
            'finished_at' => now(),
            'duration_ms' => (int) ($run->started_at?->diffInMilliseconds(now()) ?? 0),
        ]);
    }

    private function failStage(DocumentProcessingRun $run, Throwable $exception): void
    {
        $run->update([
            'status' => 'failed',
            'error' => $exception->getMessage(),
            'finished_at' => now(),
            'duration_ms' => (int) ($run->started_at?->diffInMilliseconds(now()) ?? 0),
        ]);
    }
}
