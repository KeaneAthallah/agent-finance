<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\Sihandal\MatchingService;
use App\Services\Sihandal\SihandalApiInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SIHANDAL matching stage (spec §17, §52): queries the system of
 * record for candidate matches of the extracted financial data.
 * Advisory by design — if SIHANDAL is unreachable the document
 * keeps its post-validation state and the failure is recorded on
 * the processing run (spec §50); it never blocks the pipeline.
 */
final class MatchSihandalDataJob implements ShouldQueue
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
    public $timeout = 120;

    public function __construct(
        public readonly int $documentId,
        public readonly int $version,
    ) {}

    public function handle(MatchingService $matching, SihandalApiInterface $sihandal): void
    {
        $document = Document::query()->findOrFail($this->documentId);

        // Stale-retry guard (spec §31), consistent with the other stages.
        if ((int) $document->processingRuns()->max('version') !== $this->version) {
            return;
        }

        $run = $document->processingRuns()->create([
            'version' => $this->version,
            'stage' => 'sihandal_matching',
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $matches = $matching->match($document, $sihandal, $this->version);

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'duration_ms' => (int) ($run->started_at?->diffInMilliseconds(now()) ?? 0),
            ]);

            Log::info('SIHANDAL matching completed', [
                'document_id' => $document->id,
                'version' => $this->version,
                'candidates' => $matches->count(),
            ]);
        } catch (Throwable $exception) {
            // Matching is advisory: the document keeps its validated
            // state and the failure is surfaced via the run row (§50).
            $run->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'finished_at' => now(),
                'duration_ms' => (int) ($run->started_at?->diffInMilliseconds(now()) ?? 0),
            ]);

            Log::warning('SIHANDAL matching failed', [
                'document_id' => $document->id,
                'version' => $this->version,
                'error' => $exception->getMessage(),
            ]);
        }

        // Pipeline continues: embeddings for the RAG knowledge
        // base (spec §21) — same processing version, advisory.
        GenerateEmbeddingsJob::dispatch($document->id, $this->version);
    }
}
