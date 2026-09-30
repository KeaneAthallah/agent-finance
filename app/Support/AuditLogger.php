<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Single write path for the audit trail (spec §35): every security-relevant
 * action — lifecycle transitions, review decisions, downloads, deletes —
 * goes through here so actor identity (human|ai|system) is recorded
 * consistently. Failures to log must never break the business action;
 * they surface in the structured log instead.
 */
final class AuditLogger
{
    /**
     * Record an audit entry. The actor is explicit: the authenticated user,
     * an explicit AI marker, or the system (jobs, automation).
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        ?User $actor = null,
        ActorType $actorType = ActorType::System,
        ?Request $request = null,
        array $properties = [],
    ): ?AuditLog {
        try {
            return AuditLog::query()->create([
                'user_id' => $actor?->id,
                'actor_type' => $actorType->value,
                'action' => $action,
                'subject_type' => $subject !== null ? $subject->getMorphClass() : null,
                'subject_id' => $subject?->getKey(),
                'ip_address' => $actorType === ActorType::Human
                    ? $request?->ip()
                    : null,
                'user_agent' => $actorType === ActorType::Human
                    ? (string) str($request?->userAgent() ?? '')->limit(512)
                    : null,
                'properties' => $properties !== [] ? $properties : null,
            ]);
        } catch (\Throwable $exception) {
            // Auditing must never take the application down (§35), but the
            // gap must be loud, not silent.
            Log::error('Audit log write failed', [
                'action' => $action,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
