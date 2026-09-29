<?php

namespace App\Services\Ai;

/**
 * Result of whole-document analysis/classification.
 */
final class AiDocumentResult
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $documentType,
        public readonly float $confidence,
        public readonly array $meta = [],
    ) {}
}
