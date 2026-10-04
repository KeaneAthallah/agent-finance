<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\Ai\AIProviderInterface;
use App\Services\Ai\Chunker;
use App\Services\Ai\KnowledgeContent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * RAG embedding stage (spec §21, §45): chunks the
 * document's whitelisted content — the completed
 * extraction payload plus its extracted page text —
 * and stores the provider embedding per chunk.
 * Advisory by design: an embedding failure is recorded
 * on the processing run and never blocks the pipeline
 * (§50). Raw, unvalidated uploads are never chunked.
 */
final class GenerateEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int
     */
    public $tries = 2;

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

    public function handle(AIProviderInterface $ai, Chunker $chunker, KnowledgeContent $knowledge): void
    {
        $document = Document::query()->findOrFail($this->documentId);

        // Stale-retry guard (spec §31), consistent with the other stages.
        if ((int) $document->processingRuns()->max('version') !== $this->version) {
            return;
        }

        $run = $document->processingRuns()->create([
            'version' => $this->version,
            'stage' => 'embedding',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            // Whitelisted content only: a document enters the
            // knowledge base once its extraction completed.
            $extraction = $document->extractions()
                ->where('status', 'completed')
                ->orderByDesc('version')
                ->first();

            if ($extraction === null) {
                $run->update([
                    'status' => 'completed',
                    'finished_at' => now(),
                    'duration_ms' => (int) ($run->started_at?->diffInMilliseconds(now()) ?? 0),
                ]);

                return;
            }

            $content = $knowledge->content($document, $extraction->payload);

            // Replace this version's chunks (idempotent re-runs, §38).
            $document->chunks()->where('version', $this->version)->delete();

            $model = (string) config('agent-finance.ai.embedding_model', 'text-embedding-3-small');

            $chunks = 0;

            foreach ($chunker->chunk($content) as $chunk) {
                $vector = $ai->generateEmbedding($chunk['content'], $model)->vector;

                $document->chunks()->create([
                    'version' => $this->version,
                    'chunk_index' => $chunk['index'],
                    'content' => $chunk['content'],
                    'embedding' => $vector,
                    'opd_id' => $document->opd_id,
                    'fiscal_year' => $document->fiscal_year,
                    'document_type' => $document->documentType?->code,
                ]);

                $chunks++;
            }

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'duration_ms' => (int) ($run->started_at?->diffInMilliseconds(now()) ?? 0),
                'error' => null,
            ]);

            Log::info('Embeddings generated', [
                'document_id' => $document->id,
                'version' => $this->version,
                'chunks' => $chunks,
                'model' => $model,
            ]);
        } catch (Throwable $exception) {
            // Advisory stage: the document keeps its state and
            // the failure is surfaced via the run row (§50).
            $run->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'finished_at' => now(),
                'duration_ms' => (int) ($run->started_at?->diffInMilliseconds(now()) ?? 0),
            ]);

            Log::warning('Embedding generation failed', [
                'document_id' => $document->id,
                'version' => $this->version,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
