<?php

namespace App\Services\Validation\Rules;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Services\Validation\ValidationContext;
use App\Services\Validation\ValidationFinding;
use App\Services\Validation\ValidationRule;

/**
 * Every official financial document must carry a document number (spec §15):
 * without one it cannot be reconciled against SIHANDAL in Phase 6.
 */
final class MissingDocumentNumberRule implements ValidationRule
{
    public function type(): string
    {
        return AnomalyType::MissingDocumentNumber->value;
    }

    /**
     * @return array<int, ValidationFinding>
     */
    public function check(ValidationContext $context): array
    {
        if ($context->value('document_number') !== null) {
            return [];
        }

        return [
            new ValidationFinding(
                type: AnomalyType::MissingDocumentNumber,
                severity: AnomalySeverity::High,
                title: 'Document number missing',
                detail: 'No document number was found in the extraction; the document cannot be reconciled with SIHANDAL.',
                evidence: [
                    'field' => 'document_number',
                    'extraction_version' => $context->extraction->version,
                ],
            ),
        ];
    }
}
