<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\Extraction\PdfPageRenderer;
use App\Services\Ocr\OcrProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Page-level text finalization (spec §9): pages with no embedded text get
 * rendered to images and OCR'd. Fully-digital documents skip through.
 */
final class ExtractTextJob implements ShouldQueue
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
    public $timeout = 1200;

    public function __construct(
        public readonly int $documentId,
        public readonly int $version,
    ) {}

    public function handle(OcrProviderInterface $ocr, PdfPageRenderer $renderer): void
    {
        $document = Document::query()->findOrFail($this->documentId);

        if ((int) $document->processingRuns()->max('version') !== $this->version) {
            return;
        }

        $run = $document->processingRuns()->create([
            'version' => $this->version,
            'stage' => 'ocr',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $pages = $document->pages()->orderBy('page_number')->get();

            $disk = (string) config('agent-finance.documents.disk');

            foreach ($pages as $page) {
                $needsOcr = $ocr->isRequired($page->extracted_text);

                if (! $needsOcr) {
                    $page->update(['processing_status' => 'done']);

                    continue;
                }

                // Only PDFs can be rendered to page images for now; image
                // uploads ARE the page image already.
                if (strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION)) === 'pdf') {
                    $page->update([
                        'image_path' => $renderer->renderPage($disk, $document->file_path, $page->page_number),
                    ]);
                } elseif (str_starts_with($document->mime_type, 'image/')) {
                    $page->update(['image_path' => $document->file_path]);
                }

                // image_path is stored relative to the documents disk, but the
                // OCR provider needs a local absolute path for tesseract.
                $ocrInput = filled($page->image_path)
                    ? Storage::disk($disk)->path($page->image_path)
                    : '';

                $result = $ocr->process($ocrInput);

                $page->update([
                    'extracted_text' => $result->text !== '' ? $result->text : $page->extracted_text,
                    'ocr_confidence' => (int) round($result->confidence * 100),
                    'text_source' => 'ocr',
                    'language' => $result->language,
                    'processing_status' => 'done',
                    'error' => null,
                ]);
            }

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'duration_ms' => (int) ($run->started_at?->diffInMilliseconds(now()) ?? 0),
            ]);

            $document->update(['status' => DocumentStatus::AiAnalysis]);

            ClassifyDocumentJob::dispatch($document->id, $this->version);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'finished_at' => now(),
                'duration_ms' => (int) ($run->started_at?->diffInMilliseconds(now()) ?? 0),
            ]);

            $document->update(['status' => DocumentStatus::OcrFailed]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $document = Document::query()->find($this->documentId);

        if ($document !== null) {
            $document->update(['status' => DocumentStatus::OcrFailed]);
        }
    }
}
