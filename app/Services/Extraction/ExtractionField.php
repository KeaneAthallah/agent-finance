<?php

namespace App\Services\Extraction;

/**
 * One field inside an ExtractionSchema.
 */
final class ExtractionField
{
    /**
     * @param  string  $name  machine key stored on document_extraction_fields
     * @param  string  $label  human label for the review UI
     * @param  string  $type  JSON-schema type: string | number | integer
     * @param  array<int, string>|null  $enum  allowed values, when constrained
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $type = 'string',
        public readonly bool $required = false,
        public readonly ?string $description = null,
        public readonly ?array $enum = null,
    ) {}
}
