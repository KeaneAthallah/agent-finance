<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentType;
use App\Services\Ai\AiDocumentRequest;
use App\Services\Ai\AIProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * AI classification (spec §6, §10): the configured AI provider classifies the
 * document against the document_types catalog. The result is advisory —
 * it sets document_type_id and the extraction_complete state; human review
 * comes later (spec §14).
 */
final class ClassifyDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int
     */
    public $tries = 3;

    /**
     * @var int
     */
    public $backoff = 60;

    /**
     * @var int
     */
    public $timeout = 300;

    public function __construct(
        public readonly int $documentId,
        public readonly int $version,
    ) {}

    public function handle(AIProviderInterface $ai): void
    {
        $document = Document::query()->findOrFail($this->documentId);

        if ((int) $document->processingRuns()->max('version') !== $this->version) {
            return;
        }

        $run = $document->processingRuns()->create([
            'version' => $this->version,
            'stage' => 'classification',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $pages = $document->pages()
                ->orderBy('page_number')
                ->get()
                ->map(fn ($page) => [
                    'page' => $page->page_number,
                    'text' => (string) $page->extracted_text,
                ])
                ->values()
                ->all();

            $catalog = DocumentType::query()->where('is_active', true)->pluck('code')->implode(', ');

            $result = $ai->analyzeDocument(new AiDocumentRequest(
                pages: $pages,
                hint: "Classify as one of: {$catalog}",
            ));

            $type = DocumentType::query()
                ->where('code', strtolower($result->documentType))
                ->orWhere('name', $result->documentType)
                ->first();

            $document->update([
                'document_type_id' => $type !== null ? $type->id : $document->document_type_id,
                'status' => DocumentStatus::ExtractionComplete,
            ]);

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'duration_ms' => (int) ($run->started_at?->diffInMilliseconds(now()) ?? 0),
            ]);

            // Schema-constrained extraction runs right after classification
            // (spec §10) — same processing version, same chain.
            ExtractFinancialDataJob::dispatch($document->id, $this->version);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'finished_at' => now(),
                'duration_ms' => (int) ($run->started_at?->diffInMilliseconds(now()) ?? 0),
            ]);

            $document->update(['status' => DocumentStatus::AiFailed]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $document = Document::query()->find($this->documentId);

        if ($document !== null) {
            $document->update(['status' => DocumentStatus::AiFailed]);
        }
    }
}
