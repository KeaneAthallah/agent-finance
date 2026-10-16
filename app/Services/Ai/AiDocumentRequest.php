<?php

namespace App\Services\Ai;

/**
 * Request payload for whole-document analysis/classification.
 *
 * @param  array<int, array{page: int, text: string, image?: string}>  $pages  image is a base64 data URI when vision is enabled
 */
final class AiDocumentRequest
{
    /**
     * @param  array<int, array{page: int, text: string, image?: string}>  $pages
     */
    public function __construct(
        public readonly array $pages,
        public readonly string $hint = '',
    ) {}
}
