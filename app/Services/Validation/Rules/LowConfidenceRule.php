<?php

namespace App\Services\Validation\Rules;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Services\Validation\ValidationContext;
use App\Services\Validation\ValidationFinding;
use App\Services\Validation\ValidationRule;

/**
 * Confidence gating (spec §12, §15): a field the AI extracted with low
 * confidence is suspect — visible, never hidden. A required field with no
 * value at all is a blocking finding (the document is incomplete). Optional
 * fields the provider simply did not find produce no finding — absence of a
 * non-required datum is normal, not an anomaly.
 */
final class LowConfidenceRule implements ValidationRule
{
    public function type(): string
    {
        return AnomalyType::LowConfidence->value;
    }

    /**
     * @return array<int, ValidationFinding>
     */
    public function check(ValidationContext $context): array
    {
        $findings = [];

        foreach ($context->fields as $field) {
            $confidence = $field->confidence;
            $hasValue = $field->value !== null && trim((string) $field->value) !== '';
            $isRequired = in_array($field->field, $context->requiredFields, true);

            // Required but absent: blocking, the document cannot be trusted.
            // An absent optional field is normal (spec §12) — skip it.
            if (! $hasValue) {
                if ($isRequired) {
                    $findings[] = new ValidationFinding(
                        type: AnomalyType::LowConfidence,
                        severity: AnomalySeverity::High,
                        title: sprintf('Required field missing: %s', str($field->field)->replace('_', ' ')),
                        detail: 'The schema requires this field but the extraction found no value.',
                        evidence: [
                            'field' => $field->field,
                            'confidence' => $confidence,
                            'value' => $field->value,
                            'required' => true,
                        ],
                    );
                }

                continue;
            }

            if ($confidence === null || $confidence >= $context->highConfidence) {
                continue;
            }

            $belowLow = $confidence < $context->lowConfidence;

            $findings[] = new ValidationFinding(
                type: AnomalyType::LowConfidence,
                severity: $belowLow ? AnomalySeverity::Warning : AnomalySeverity::Info,
                title: sprintf('Low confidence on %s', str($field->field)->replace('_', ' ')),
                detail: sprintf(
                    'The AI scored this field at %d%% (threshold %d%%).',
                    $confidence,
                    (int) round($context->highConfidence),
                ),
                evidence: [
                    'field' => $field->field,
                    'confidence' => $confidence,
                    'value' => $field->value,
                ],
            );
        }

        return $findings;
    }
}
