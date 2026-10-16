<?php

namespace App\Services\Ai;

/**
 * Request for schema-constrained structured extraction (spec §10, §11).
 */
final class AiExtractionRequest
{
    /**
     * @param  array<int, array{page: int, text: string, image?: string}>  $pages  image is a base64 data URI when vision is enabled
     * @param  array<string, mixed>|null  $schema  JSON schema the output must satisfy
     */
    public function __construct(
        public readonly array $pages,
        public readonly string $documentType,
        public readonly ?array $schema = null,
        public readonly string $promptName = '',
        public readonly string $promptVersion = '',
    ) {}
}
