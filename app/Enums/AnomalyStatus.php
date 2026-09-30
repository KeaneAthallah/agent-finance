<?php

namespace App\Enums;

/**
 * Anomaly lifecycle (spec §15): open anomalies block auto-approval; humans
 * resolve or dismiss them during review.
 */
enum AnomalyStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
