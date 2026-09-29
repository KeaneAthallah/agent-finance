<?php

namespace App\Services\Extraction;

/**
 * A typed extraction schema (spec §10–§11): the field catalog the AI must
 * fill for one document type plus the JSON-schema representation sent to the
 * provider. Schemas are pure data — no provider or Eloquent coupling.
 */
final class ExtractionSchema
{
    /**
     * @param  string  $type  document type code this schema serves (e.g. "spm")
     * @param  string  $version  schema version, bumped on breaking field changes
     * @param  array<int, ExtractionField>  $fields
     * @param  string  $systemPrompt  role instructions for the provider
     */
    public function __construct(
        public readonly string $type,
        public readonly string $version,
        public readonly array $fields,
        public readonly string $systemPrompt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toJsonSchema(): array
    {
        $properties = [];

        foreach ($this->fields as $field) {
            $properties[$field->name] = array_filter([
                'type' => $field->type,
                'description' => $field->description,
                'enum' => $field->enum,
            ], static fn ($v) => $v !== null);
        }

        return [
            'name' => 'extraction_'.$this->type,
            'type' => 'object',
            'properties' => $properties,
            'required' => array_values(array_map(
                static fn (ExtractionField $f) => $f->name,
                array_filter($this->fields, static fn (ExtractionField $f) => $f->required),
            )),
            'additionalProperties' => false,
        ];
    }
}
