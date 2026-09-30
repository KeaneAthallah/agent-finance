<?php

namespace App\Services\Validation\Rules;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Models\Document;
use App\Services\Validation\ValidationContext;
use App\Services\Validation\ValidationFinding;
use App\Services\Validation\ValidationRule;

/**
 * Cross-document duplicate scan (spec §15): identical file hashes are already
 * blocked at upload (§24), so this rule catches the subtler case — the same
 * document number reused within the same OPD, i.e. likely double-submission
 * of the same obligation (possibly a re-scan under a new filename).
 */
final class DuplicateScanRule implements ValidationRule
{
    public function type(): string
    {
        return AnomalyType::Duplicate->value;
    }

    /**
     * @return array<int, ValidationFinding>
     */
    public function check(ValidationContext $context): array
    {
        $documentNumber = $context->value('document_number');

        if ($documentNumber === null) {
            return [];
        }

        $candidates = Document::query()
            ->where('opd_id', $context->document->opd_id)
            ->where('document_number', $documentNumber)
            ->whereKeyNot($context->document->getKey())
            ->limit(5)
            ->get();

        $findings = [];

        foreach ($candidates as $candidate) {
            $findings[] = new ValidationFinding(
                type: AnomalyType::Duplicate,
                severity: AnomalySeverity::Critical,
                title: 'Possible duplicate: same document number in this OPD',
                detail: sprintf(
                    'Document #%d ("%s") carries the same document number "%s".',
                    $candidate->getKey(),
                    $candidate->original_filename,
                    $documentNumber,
                ),
                evidence: [
                    'duplicate_document_id' => $candidate->getKey(),
                    'field' => 'document_number',
                    'matched_on' => 'document_number',
                    'opd_id' => $context->document->opd_id,
                ],
            );
        }

        return $findings;
    }
}
