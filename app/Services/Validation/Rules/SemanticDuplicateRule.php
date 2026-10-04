<?php

namespace App\Services\Validation\Rules;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\Ai\AIProviderInterface;
use App\Services\Ai\KnowledgeContent;
use App\Services\Validation\ValidationContext;
use App\Services\Validation\ValidationFinding;
use App\Services\Validation\ValidationRule;
use App\Support\Similarity;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Semantic duplicate scan (spec §15): near-identical document
 * content within the same OPD — a re-scan of the same
 * obligation under a new filename. The document's whitelisted
 * content is embedded on the fly and compared against the
 * knowledge-base chunks of the OPD's other documents.
 * Advisory by design: without embeddings there is no semantic
 * signal and the rule stays silent (never blocks, §50).
 */
final class SemanticDuplicateRule implements ValidationRule
{
    /** Near-identical content only — paraphrases stay below this. */
    private const SIMILARITY_THRESHOLD = 0.95;

    private const MAX_COMPARED_CHUNKS = 500;

    public function __construct(
        private readonly AIProviderInterface $ai,
        private readonly KnowledgeContent $knowledge,
    ) {}

    public function type(): string
    {
        return AnomalyType::Duplicate->value;
    }

    /**
     * @return array<int, ValidationFinding>
     */
    public function check(ValidationContext $context): array
    {
        $payload = $context->extraction->payload;

        $content = $this->knowledge->content($context->document, $payload);

        if (trim($content) === '') {
            return [];
        }

        try {
            $vector = $this->ai->generateEmbedding(
                $content,
                (string) config('agent-finance.ai.embedding_model', 'text-embedding-3-small'),
            )->vector;
        } catch (Throwable) {
            return [];
        }

        $chunks = DocumentChunk::query()
            ->whereHas('document', fn (Builder $q): Builder => $q
                ->where('opd_id', $context->document->opd_id)
                ->whereKeyNot($context->document->getKey()))
            ->whereNotNull('embedding')
            ->limit(self::MAX_COMPARED_CHUNKS)
            ->get();

        // Best similarity per candidate document.
        $best = [];

        foreach ($chunks as $chunk) {
            $similarity = Similarity::cosine($vector, $chunk->embedding ?? []);

            if ($similarity >= self::SIMILARITY_THRESHOLD) {
                $best[$chunk->document_id] = max(
                    $best[$chunk->document_id] ?? 0.0,
                    $similarity,
                );
            }
        }

        $findings = [];

        foreach ($best as $documentId => $similarity) {
            $candidate = Document::query()->find($documentId);

            if ($candidate === null) {
                continue;
            }

            $findings[] = new ValidationFinding(
                type: AnomalyType::Duplicate,
                severity: AnomalySeverity::High,
                title: 'Possible duplicate: near-identical content in this OPD',
                detail: sprintf(
                    'Document #%d ("%s") has %d%% similar content.',
                    $documentId,
                    $candidate->original_filename,
                    (int) round($similarity * 100),
                ),
                evidence: [
                    'duplicate_document_id' => $documentId,
                    'similarity' => round($similarity, 4),
                    'matched_on' => 'content_embedding',
                    'opd_id' => $context->document->opd_id,
                ],
            );
        }

        return $findings;
    }
}
