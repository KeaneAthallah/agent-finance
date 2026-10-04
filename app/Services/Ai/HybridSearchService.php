<?php

namespace App\Services\Ai;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Support\Similarity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Hybrid search over the document knowledge base (spec §21, §45):
 * SQL metadata filters (OPD isolation, fiscal year, document type)
 * combined with vector cosine similarity. Similarity is computed in
 * PHP so it behaves identically on SQLite (tests) and MySQL (prod).
 */
final class HybridSearchService
{
    /** Weight of the lexical (SQL LIKE) signal vs the vector signal. */
    private const LEXICAL_WEIGHT = 0.3;

    private const VECTOR_WEIGHT = 0.7;

    /**
     * @return Collection<int, array{document_id: int, chunk_index: int, score: float, snippet: string, document_number: string|null, document_type: string|null, fiscal_year: int|null, original_filename: string}>
     */
    public function search(
        string $query,
        User $user,
        ?int $fiscalYear = null,
        ?string $documentType = null,
        int $limit = 10,
    ): Collection {
        $chunks = $this->candidates($query, $user, $fiscalYear, $documentType, $limit * 5);

        if ($chunks->isEmpty()) {
            return new Collection;
        }

        $queryVector = $this->embed($query);

        $scored = $chunks
            ->map(function (DocumentChunk $chunk) use ($query, $queryVector): ?array {
                $score = $this->lexicalScore($query, $chunk->content) * self::LEXICAL_WEIGHT
                    + ($queryVector !== null && $chunk->embedding !== null
                        ? Similarity::cosine($queryVector, $chunk->embedding) * self::VECTOR_WEIGHT
                        : 0.0);

                if ($score <= 0.0) {
                    return null;
                }

                return [
                    'document_id' => (int) $chunk->document_id,
                    'chunk_index' => (int) $chunk->chunk_index,
                    'score' => round($score, 4),
                    'snippet' => $this->snippet($chunk->content),
                    'document_number' => $chunk->document->document_number,
                    'document_type' => $chunk->document_type,
                    'fiscal_year' => $chunk->fiscal_year,
                    'original_filename' => $chunk->document->original_filename,
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $hit): float => $hit['score'])
            ->values()
            ->take($limit);

        return new Collection($scored->all());
    }

    /**
     * OPD-scoped candidates: metadata filters first (SQL), vector
     * similarity only re-ranks — the tenancy boundary is never
     * crossed client-side (§36).
     *
     * @return EloquentCollection<int, DocumentChunk>
     */
    private function candidates(
        string $query,
        User $user,
        ?int $fiscalYear,
        ?string $documentType,
        int $limit,
    ): EloquentCollection {
        return DocumentChunk::query()
            ->whereHas('document', fn (Builder $q): Builder => $q->accessibleBy($user))
            ->when($fiscalYear !== null, fn (Builder $q): Builder => $q->where('fiscal_year', $fiscalYear))
            ->when($documentType !== null, fn (Builder $q): Builder => $q->where('document_type', $documentType))
            ->where(fn (Builder $q): Builder => $q
                ->whereNotNull('embedding')
                ->orWhere(fn (Builder $q): Builder => $q->where('content', 'like', '%'.$this->escapeLike($query).'%')))
            ->with('document:id,document_number,original_filename')
            ->limit($limit)
            ->get();
    }

    /** @return array<int, float>|null */
    private function embed(string $query): ?array
    {
        try {
            $provider = app(AIProviderInterface::class);

            return $provider->generateEmbedding(
                $query,
                (string) config('agent-finance.ai.embedding_model', 'text-embedding-3-small'),
            )->vector;
        } catch (\Throwable $exception) {
            // Lexical-only search degrades gracefully (§50).
            Log::warning('Embedding unavailable for hybrid search', ['error' => $exception->getMessage()]);

            return null;
        }
    }

    private function lexicalScore(string $query, string $content): float
    {
        $terms = $this->terms($query);

        if ($terms === []) {
            return 0.0;
        }

        $contentTerms = $this->terms($content);
        $hits = 0;

        foreach ($terms as $term) {
            if (in_array($term, $contentTerms, true)) {
                $hits++;
            }
        }

        return $hits / count($terms);
    }

    /** @return array<int, string> */
    private function terms(string $text): array
    {
        $terms = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($terms, static fn (string $t): bool => mb_strlen($t) > 2)));
    }

    private function escapeLike(string $query): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
    }

    private function snippet(string $content): string
    {
        return mb_strlen($content) > 160 ? mb_substr($content, 0, 157).'...' : $content;
    }
}
