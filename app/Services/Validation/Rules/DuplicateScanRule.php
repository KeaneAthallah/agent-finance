<?php

namespace App\Services\Validation\Rules;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Models\Document;
use App\Services\Validation\ValidationContext;
use App\Services\Validation\ValidationFinding;
use App\Services\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cross-document duplicate scan (spec §15, §24): identical file
 * hashes are already blocked at upload, so this rule catches the
 * subtler cases — the same document number reused within the same
 * OPD (likely double submission of the same obligation), and the
 * same payee + amount + date combination (the same obligation
 * paid twice under different numbers).
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
        $findings = [];

        $documentNumber = $context->value('document_number');

        if ($documentNumber !== null) {
            $candidates = Document::query()
                ->where('opd_id', $context->document->opd_id)
                ->where('document_number', $documentNumber)
                ->whereKeyNot($context->document->getKey())
                ->limit(5)
                ->get();

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
        }

        $findings = array_merge($findings, $this->compositeFindings($context));

        return $findings;
    }

    /**
     * Same payee + amount + document date within the same OPD and
     * fiscal year: the same obligation paid twice under different
     * numbers. Field values are compared on the extraction field
     * rows, so the check stays portable across SQLite (tests) and
     * MySQL (production).
     *
     * @return array<int, ValidationFinding>
     */
    private function compositeFindings(ValidationContext $context): array
    {
        $payee = $context->value('payee_name');
        $amount = $context->value('amount');
        $date = $context->value('document_date');

        if ($payee === null || $amount === null || $date === null) {
            return [];
        }

        $candidates = Document::query()
            ->where('opd_id', $context->document->opd_id)
            ->where('fiscal_year', $context->document->fiscal_year)
            ->whereKeyNot($context->document->getKey())
            ->whereHas('extractions', fn (Builder $q): Builder => $q->where('status', 'completed'))
            ->whereHas('extractions.fields', fn (Builder $q): Builder => $q
                ->where('field', 'payee_name')
                ->where('value', $payee))
            ->whereHas('extractions.fields', fn (Builder $q): Builder => $q
                ->where('field', 'amount')
                ->where('value', $amount))
            ->whereHas('extractions.fields', fn (Builder $q): Builder => $q
                ->where('field', 'document_date')
                ->where('value', $date))
            ->limit(5)
            ->get();

        $findings = [];

        foreach ($candidates as $candidate) {
            $findings[] = new ValidationFinding(
                type: AnomalyType::Duplicate,
                severity: AnomalySeverity::High,
                title: 'Possible duplicate: same payee, amount and date in this OPD',
                detail: sprintf(
                    'Document #%d ("%s") pays "%s" the same amount %s on %s.',
                    $candidate->getKey(),
                    $candidate->original_filename,
                    $payee,
                    $amount,
                    $date,
                ),
                evidence: [
                    'duplicate_document_id' => $candidate->getKey(),
                    'field' => 'payee_name',
                    'matched_on' => 'payee_amount_date',
                    'opd_id' => $context->document->opd_id,
                ],
            );
        }

        return $findings;
    }
}
