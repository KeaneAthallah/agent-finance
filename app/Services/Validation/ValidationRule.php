<?php

namespace App\Services\Validation;

/**
 * A deterministic validation rule (spec §15): pure function from a
 * ValidationContext to zero or more findings. Rules never mutate state and
 * never call the AI — they are the factual counterpart to AI extraction.
 */
interface ValidationRule
{
    /**
     * Machine identifier persisted on document_anomalies.type.
     */
    public function type(): string;

    /**
     * @return array<int, ValidationFinding>
     */
    public function check(ValidationContext $context): array;
}
