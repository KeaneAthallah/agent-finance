<?php

namespace App\Enums;

/**
 * Anomaly types emitted by the deterministic validation engine (spec §15).
 * The engine is factual: every anomaly is an indicator, never an AI verdict
 * (spec §25).
 */
enum AnomalyType: string
{
    case TotalMismatch = 'total_mismatch';
    case FiscalYearMismatch = 'fiscal_year_mismatch';
    case MissingDocumentNumber = 'missing_document_number';
    case LowConfidence = 'low_confidence';
    case Duplicate = 'duplicate';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
