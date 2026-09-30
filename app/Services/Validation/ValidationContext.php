<?php

namespace App\Services\Validation;

use App\Models\Document;
use App\Models\DocumentExtraction;
use App\Models\DocumentExtractionField;
use Illuminate\Support\Collection;

/**
 * Everything a validation rule may look at (spec §15): the document, the
 * extraction pass under test, and its per-field rows keyed by field name.
 * Rules are deterministic and read-only — they never mutate state.
 */
final class ValidationContext
{
    /**
     * @param  Collection<string, DocumentExtractionField>  $fields  keyed by field name
     * @param  array<int, string>  $requiredFields  schema-required field names
     */
    public function __construct(
        public readonly Document $document,
        public readonly DocumentExtraction $extraction,
        public readonly Collection $fields,
        public readonly float $highConfidence,
        public readonly float $lowConfidence,
        public readonly array $requiredFields = [],
    ) {}

    /**
     * The extracted value for a field, or null when absent/empty.
     */
    public function value(string $field): ?string
    {
        $value = $this->fields->get($field)?->value;

        if ($value === null || trim($value) === '') {
            return null;
        }

        return $value;
    }

    /**
     * Field confidence (0–100) or null when the field was not scored.
     */
    public function confidence(string $field): ?int
    {
        return $this->fields->get($field)?->confidence;
    }
}
