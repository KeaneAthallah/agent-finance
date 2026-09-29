<?php

namespace App\Services\Ai;

/**
 * Embedding result for the RAG layer (spec §21).
 */
final class AiEmbeddingResult
{
    /**
     * @param  array<int, float>  $vector
     */
    public function __construct(
        public readonly array $vector,
        public readonly string $model,
    ) {}
}
