<?php

namespace App\Services\Validation\Rules;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Services\Validation\ValidationContext;
use App\Services\Validation\ValidationFinding;
use App\Services\Validation\ValidationRule;

/**
 * Extracted fiscal year vs the year the uploader assigned (spec §15): a
 * mismatch usually means the wrong document set was chosen at upload.
 * Warning severity — frequently explainable, always worth a look.
 */
final class FiscalYearMismatchRule implements ValidationRule
{
    public function type(): string
    {
        return AnomalyType::FiscalYearMismatch->value;
    }

    /**
     * @return array<int, ValidationFinding>
     */
    public function check(ValidationContext $context): array
    {
        $extracted = $context->value('fiscal_year');

        if ($extracted === null || ! is_numeric($extracted)) {
            return [];
        }

        $assigned = $context->document->fiscal_year;

        if ($assigned === null) {
            return [];
        }

        if ((int) $extracted === $assigned) {
            return [];
        }

        return [
            new ValidationFinding(
                type: AnomalyType::FiscalYearMismatch,
                severity: AnomalySeverity::Warning,
                title: 'Fiscal year differs from the assigned budget year',
                detail: sprintf(
                    'Document was uploaded for fiscal year %d but the extracted fiscal year is %s.',
                    $assigned,
                    $extracted,
                ),
                evidence: [
                    'field' => 'fiscal_year',
                    'expected' => $assigned,
                    'actual' => (int) $extracted,
                ],
            ),
        ];
    }
}
