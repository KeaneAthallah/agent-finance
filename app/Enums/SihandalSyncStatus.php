<?php

namespace App\Enums;

/**
 * Lifecycle of a SIHANDAL sync attempt (spec §48, §50). Pending syncs
 * are retryable — SIHANDAL being unavailable never loses a document.
 */
enum SihandalSyncStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
}
