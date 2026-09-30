<?php

namespace App\Enums;

/**
 * Review decisions a human verifier can make (spec §14). The enum is the
 * single source of truth for the decision strings persisted on
 * document_reviews and mirrored in the API/UI.
 */
enum ReviewDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ReanalysisRequested = 'reanalysis_requested';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
