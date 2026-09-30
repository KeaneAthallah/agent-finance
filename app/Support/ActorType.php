<?php

namespace App\Support;

/**
 * Who performed an audited action (spec §14, §35): manual corrections are
 * human, pipeline/AI-derived changes are ai or system. This distinction is
 * the core of the audit trail — "AI recommends, humans decide" is verifiable
 * only when the actor of every change is recorded.
 */
enum ActorType: string
{
    case Human = 'human';
    case Ai = 'ai';
    case System = 'system';
}
