<?php

namespace App\Enums;

/**
 * Anomaly severity levels (spec §15): factual indicators, not verdicts.
 */
enum AnomalySeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case High = 'high';
    case Critical = 'critical';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
