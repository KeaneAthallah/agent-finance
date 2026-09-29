<?php

namespace App\Services\Extraction;

use App\Models\Document;
use Illuminate\Support\Str;

/**
 * Resolves the extraction schema for a document (spec §10, §11). Per-type
 * schemas cover the common financial documents; unknown types fall back to a
 * generic financial-document schema so nothing is unextractable by accident.
 */
final class ExtractionSchemaRegistry
{
    /**
     * Field groups shared by several schemas.
     *
     * @return array<int, ExtractionField>
     */
    private static function identityFields(): array
    {
        return [
            new ExtractionField('document_number', 'Document number', 'string', true, 'The official document number, e.g. "800/123/SPM/2026" — copy exactly, including punctuation.'),
            new ExtractionField('document_date', 'Document date', 'string', true, 'The document date in YYYY-MM-DD format.'),
            new ExtractionField('fiscal_year', 'Fiscal year', 'integer', true, 'The budget (anggaran) year the document belongs to, e.g. 2026.'),
            new ExtractionField('opd_name', 'OPD name', 'string', false, 'Name of the government work unit (SKPD/OPD) issuing or receiving the document.'),
        ];
    }

    /**
     * @return array<int, ExtractionField>
     */
    private static function amountFields(): array
    {
        return [
            new ExtractionField('amount', 'Amount', 'number', true, 'The total monetary value in IDR as a plain number (no thousand separators, no "Rp").'),
            new ExtractionField('amount_in_words', 'Amount in words', 'string', false, 'The terbilang (amount in words) line, copied verbatim.'),
        ];
    }

    /**
     * @return array<int, ExtractionField>
     */
    private static function partyFields(): array
    {
        return [
            new ExtractionField('payee_name', 'Payee / vendor', 'string', false, 'The party receiving payment (nama penerima / penyedia).'),
            new ExtractionField('account_number', 'Account number', 'string', false, 'Bank account number of the payee, digits only.'),
            new ExtractionField('bank_name', 'Bank name', 'string', false, 'Name of the payee bank.'),
        ];
    }

    public function __construct() {}

    /**
     * Schema for a document type code; unknown types get the generic schema.
     */
    public function forType(?string $typeCode): ExtractionSchema
    {
        $typeCode = $typeCode !== null ? Str::lower($typeCode) : null;

        $fields = match ($typeCode) {
            'spm' => array_merge(self::identityFields(), self::amountFields(), [
                new ExtractionField('spm_kind', 'SPM kind', 'string', false, 'One of: LS, UP, TU, GU, Nihil.', ['LS', 'UP', 'TU', 'GU', 'Nihil']),
                new ExtractionField('spp_number', 'SPP number', 'string', false, 'The SPP number this SPM is based on.'),
                new ExtractionField('budget_account', 'Budget account', 'string', false, 'Rekening belanja code, e.g. "5.1.02.01.0001".'),
            ]),
            'sp2d' => array_merge(self::identityFields(), self::amountFields(), [
                new ExtractionField('spm_number', 'SPM number', 'string', false, 'The SPM number this SP2D executes.'),
                new ExtractionField('payment_method', 'Payment method', 'string', false, 'One of: LS, UP, TU, GU, Nihil.', ['LS', 'UP', 'TU', 'GU', 'Nihil']),
            ]),
            'spp' => array_merge(self::identityFields(), self::amountFields(), self::partyFields(), [
                new ExtractionField('spp_kind', 'SPP kind', 'string', false, 'One of: LS, UP, TU, GU, Nihil.', ['LS', 'UP', 'TU', 'GU', 'Nihil']),
                new ExtractionField('budget_account', 'Budget account', 'string', false, 'Rekening belanja code.'),
            ]),
            'invoice', 'kwitansi', 'faktur' => array_merge(self::identityFields(), self::amountFields(), self::partyFields(), [
                new ExtractionField('description', 'Description', 'string', false, 'Short description of the goods or services being paid for.'),
            ]),
            'bukti_transfer' => array_merge(self::identityFields(), self::amountFields(), self::partyFields(), [
                new ExtractionField('reference_number', 'Reference number', 'string', false, 'Bank reference / transaction ID.'),
            ]),
            'dpa', 'rka', 'dpa_perubahan' => [
                new ExtractionField('document_number', 'Document number', 'string', true, 'The official document number, copied exactly.'),
                new ExtractionField('document_date', 'Document date', 'string', true, 'The document date in YYYY-MM-DD format.'),
                new ExtractionField('fiscal_year', 'Fiscal year', 'integer', true, 'The budget year the document belongs to.'),
                new ExtractionField('opd_name', 'OPD name', 'string', true, 'Name of the government work unit the budget belongs to.'),
                new ExtractionField('total_budget', 'Total budget', 'number', true, 'Total approved budget (pagu) in IDR as a plain number.'),
            ],
            default => array_merge(self::identityFields(), self::amountFields(), [
                new ExtractionField('description', 'Description', 'string', false, 'Short description of what this document covers.'),
            ]),
        };

        $code = $typeCode ?? 'other';

        return new ExtractionSchema(
            type: $code,
            version: '1.0',
            fields: $fields,
            systemPrompt: sprintf(
                'You extract structured data from Indonesian local government financial documents '
                .'of type "%s". Copy values exactly as printed; never invent numbers. Amounts are '
                .'plain numbers without separators. Dates are YYYY-MM-DD. Use null for fields not '
                .'present in the document.',
                $code,
            ),
        );
    }

    /**
     * Schema for a document model, using its classified type when known.
     */
    public function forDocument(Document $document): ExtractionSchema
    {
        return $this->forType($document->documentType?->code);
    }
}
