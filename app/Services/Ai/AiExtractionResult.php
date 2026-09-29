<?php

namespace App\Services\Ai;

/**
 * Schema-valid structured extraction result (spec §10–§13).
 */
final class AiExtractionResult
{
    /**
     * @param array<string, mixed> $data the schema-valid payload
     * @param array<string, array{value: mixed, confidence: float, source: array<string, mixed>}> $fields per-field confidence/evidence
     * @param array<string, mixed> $meta provider/model/prompt metadata
     */
    public function __construct(
        public readonly array $data,
        public readonly array $fields,
        public readonly float $confidence,
        public readonly array $meta = [],
    ) {}
}
