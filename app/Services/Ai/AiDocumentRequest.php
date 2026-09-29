<?php

namespace App\Services\Ai;

/**
 * Request payload for whole-document analysis/classification.
 *
 * @param array<int, array{page: int, text: string}> $pages
 */
final class AiDocumentRequest
{
    /**
     * @param array<int, array{page: int, text: string}> $pages
     */
    public function __construct(
        public readonly array $pages,
        public readonly string $hint = '',
    ) {}
}
