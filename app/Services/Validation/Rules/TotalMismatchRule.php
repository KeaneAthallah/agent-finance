<?php

namespace App\Services\Validation\Rules;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Services\Validation\ValidationContext;
use App\Services\Validation\ValidationFinding;
use App\Services\Validation\ValidationRule;

/**
 * Line items vs stated total (spec §15): when the extraction carries an
 * items array with amounts, their sum must match the stated total within a
 * rounding tolerance. A mismatch is a high-severity factual indicator.
 */
final class TotalMismatchRule implements ValidationRule
{
    /**
     * Rounding tolerance in whole currency units (IDR has no minor unit in
     * practice): absorbs cent/rounding artifacts, never real gaps.
     */
    public function __construct(
        private readonly float $tolerance = 1.0,
    ) {}

    public function type(): string
    {
        return AnomalyType::TotalMismatch->value;
    }

    /**
     * @return array<int, ValidationFinding>
     */
    public function check(ValidationContext $context): array
    {
        $payload = $context->extraction->payload ?? [];

        $items = $payload['items'] ?? null;

        if (! is_array($items) || $items === []) {
            return [];
        }

        $sum = 0.0;
        $count = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $amount = $item['amount'] ?? $item['total'] ?? null;

            if (is_numeric($amount)) {
                $sum += (float) $amount;
                $count++;
            }
        }

        // Nothing summable — not this rule's business.
        if ($count === 0) {
            return [];
        }

        $stated = $context->value('amount') ?? $context->value('total_budget');

        if ($stated === null || ! is_numeric($stated)) {
            return [];
        }

        $difference = round(abs($sum - (float) $stated), 2);

        if ($difference <= $this->tolerance) {
            return [];
        }

        return [
            new ValidationFinding(
                type: AnomalyType::TotalMismatch,
                severity: AnomalySeverity::High,
                title: 'Line items do not sum to the stated total',
                detail: sprintf(
                    '%d line items sum to %s but the document states %s (difference %s).',
                    $count,
                    number_format($sum, 0, ',', '.'),
                    number_format((float) $stated, 0, ',', '.'),
                    number_format($difference, 0, ',', '.'),
                ),
                evidence: [
                    'field' => 'amount',
                    'line_items_sum' => $sum,
                    'stated_total' => (float) $stated,
                    'difference' => $difference,
                    'line_item_count' => $count,
                ],
            ),
        ];
    }
}
