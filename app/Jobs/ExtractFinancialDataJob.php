<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\AiRun;
use App\Models\AiUsage;
use App\Models\Document;
use App\Models\DocumentExtraction;
use App\Models\DocumentPage;
use App\Services\Ai\AiExtractionRequest;
use App\Services\Ai\AiExtractionResult;
use App\Services\Ai\AIProviderInterface;
use App\Services\Ai\PromptRegistry;
use App\Services\Extraction\ExtractionSchema;
use App\Services\Extraction\ExtractionSchemaRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Schema-constrained financial extraction (spec §10–§13, §30): the provider
 * fills the typed schema for the document's classified type. Every field row
 * carries confidence + evidence; the full payload is versioned on
 * document_extractions. Invalid or empty output fails loudly — never silently
 * accepted (§10).
 */
final class ExtractFinancialDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int
     */
    public $tries = 3;

    /**
     * @var array<int, int>
     */
    public $backoff = [60, 300];

    /**
     * @var int
     */
    public $timeout = 600;

    public function __construct(
        public readonly int $documentId,
        public readonly int $version,
    ) {}

    public function handle(
        AIProviderInterface $ai,
        ExtractionSchemaRegistry $registry,
        PromptRegistry $prompts,
    ): void {
        $document = Document::query()->findOrFail($this->documentId);

        // Stale-retry guard (spec §31): a late retry of an older processing
        // version must not overwrite newer extraction history.
        if ((int) $document->processingRuns()->max('version') !== $this->version) {
            return;
        }

        $schema = $registry->forDocument($document);
        $resolved = $prompts->resolveExtraction($schema->systemPrompt);

        $run = AiRun::query()->create([
            'document_id' => $document->id,
            'prompt_id' => $resolved['prompt_id'],
            'stage' => 'extraction',
            'provider' => (string) config('agent-finance.ai.provider'),
            'model' => (string) config('agent-finance.ai.default_model'),
            'prompt_name' => $resolved['prompt_name'],
            'prompt_version' => $resolved['prompt_version'],
            'status' => 'running',
        ]);

        $extraction = $document->extractions()->firstOrNew(['version' => $this->version]);
        $extraction->fill([
            'ai_run_id' => $run->id,
            'schema_name' => $schema->type,
            'schema_version' => $schema->version,
            'status' => 'running',
            'error' => null,
        ]);
        $extraction->save();

        $document->update(['status' => DocumentStatus::AiAnalysis]);

        try {
            $pages = $document->pages()
                ->orderBy('page_number')
                ->get()
                ->map(fn (DocumentPage $page): array => [
                    'page' => (int) $page->page_number,
                    'text' => (string) $page->extracted_text,
                ])
                ->values()
                ->all();

            $result = $ai->extractStructuredData(new AiExtractionRequest(
                pages: $pages,
                documentType: $schema->type,
                schema: $schema->toJsonSchema(),
                promptName: $resolved['prompt_name'],
                promptVersion: $resolved['prompt_version'],
            ));

            $this->persistResult($document, $extraction, $run, $result, $schema);

            $this->backfillDocumentMetadata($document, $extraction);

            $document->update(['status' => DocumentStatus::ExtractionComplete]);

            // Pipeline continues: deterministic validation runs right after a
            // completed extraction (spec §5, §30) — same processing version.
            ValidateDocumentJob::dispatch($document->id, $this->version);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'duration_ms' => (int) ($run->created_at ?? now())->diffInMilliseconds(now()),
            ]);

            $extraction->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
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

    /**
     * Persist the extraction payload, per-field confidence/evidence rows,
     * run completion and usage record in one transaction.
     */
    private function persistResult(
        Document $document,
        DocumentExtraction $extraction,
        AiRun $run,
        AiExtractionResult $result,
        ExtractionSchema $schema,
    ): void {
        $fields = collect($schema->fields);

        // Per-field confidence/evidence from the provider (§12); fields the
        // provider did not score get the overall confidence — visible, never hidden.
        $overall = (int) round($result->confidence * 100);

        $startedAt = $run->created_at ?? now();

        DB::transaction(function () use ($extraction, $run, $result, $fields, $overall, $startedAt): void {
            $payload = $result->data;

            $extraction->update([
                'payload' => $payload,
                'status' => 'completed',
                'confidence' => $overall,
                'error' => null,
            ]);

            $extraction->fields()->delete();

            foreach ($fields as $schemaField) {
                $value = $payload[$schemaField->name] ?? null;

                $extraction->fields()->create([
                    'field' => $schemaField->name,
                    'value' => is_scalar($value) || $value === null ? (string) ($value ?? '') : json_encode($value),
                    'confidence' => $this->fieldConfidence($result, $schemaField->name, $overall),
                    'evidence' => $this->fieldEvidence($result, $schemaField->name),
                ]);
            }

            $run->update([
                'status' => 'completed',
                'input_tokens' => isset($result->meta['input_tokens']) ? (int) $result->meta['input_tokens'] : null,
                'output_tokens' => isset($result->meta['output_tokens']) ? (int) $result->meta['output_tokens'] : null,
                'duration_ms' => (int) $startedAt->diffInMilliseconds(now()),
                'meta' => $result->meta,
            ]);

            AiUsage::query()->create([
                'ai_run_id' => $run->id,
                'provider' => $run->provider,
                'model' => $run->model,
                'input_tokens' => (int) ($result->meta['input_tokens'] ?? 0),
                'output_tokens' => (int) ($result->meta['output_tokens'] ?? 0),
                'duration_ms' => $run->duration_ms,
            ]);
        });

        Log::info('Financial extraction completed', [
            'document_id' => $document->id,
            'extraction_version' => $this->version,
            'schema' => $schema->type,
            'confidence' => $overall,
            'ai_run_id' => $run->id,
        ]);
    }

    /**
     * Backfill indexed document columns from the extraction (spec §27):
     * document_number for duplicate scans and SIHANDAL matching, amounts as
     * plain numbers into metadata for validation. Never overwrites values a
     * human may have set first — AI never has the last word on state (§18).
     */
    private function backfillDocumentMetadata(Document $document, DocumentExtraction $extraction): void
    {
        $payload = $extraction->payload ?? [];

        $metadata = $document->metadata ?? [];

        $amountFields = ['amount', 'total_budget'];

        foreach ($amountFields as $field) {
            if (isset($payload[$field]) && is_numeric($payload[$field])) {
                $metadata['extracted_'.$field] = (float) $payload[$field];
            }
        }

        $updates = [];

        $number = $payload['document_number'] ?? null;

        if (is_string($number) && trim($number) !== '' && $document->document_number === null) {
            $updates['document_number'] = trim($number);
        }

        if ($document->fiscal_year === null) {
            $fiscalYear = $payload['fiscal_year'] ?? null;

            if (is_numeric($fiscalYear)) {
                $updates['fiscal_year'] = (int) $fiscalYear;
            }
        }

        if ($updates !== []) {
            $document->update($updates);
        }

        if ($metadata !== ($document->metadata ?? [])) {
            $document->forceFill(['metadata' => $metadata])->save();
        }
    }

    private function fieldConfidence(
        AiExtractionResult $result,
        string $fieldName,
        int $overall,
    ): int {
        $field = $result->fields[$fieldName] ?? null;

        if ($field === null) {
            return $overall;
        }

        return (int) round(((float) $field['confidence']) * 100);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fieldEvidence(AiExtractionResult $result, string $fieldName): ?array
    {
        $field = $result->fields[$fieldName] ?? null;

        if ($field === null) {
            return null;
        }

        /** @var mixed $source */
        $source = $field['source'];

        if (! is_array($source)) {
            return null;
        }

        $evidence = [];

        if (isset($source['page'])) {
            $evidence['page'] = (int) $source['page'];
        }

        if (is_array($source['bbox'] ?? null)) {
            $evidence['bbox'] = array_map('floatval', $source['bbox']);
        }

        if (is_string($source['source_text'] ?? null)) {
            $evidence['source_text'] = $source['source_text'];
        }

        return $evidence === [] ? null : $evidence;
    }
}
