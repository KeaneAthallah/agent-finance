<?php

namespace App\Enums;

/**
 * Lifecycle of a SIHANDAL match candidate (spec §17): produced by the
 * matching job, decided by a human — confirmed candidates become the
 * basis of a sync.
 */
enum SihandalMatchStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
}
