<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Audit trail page (spec §14, §44): the platform's answer to "who changed
 * what, when, and as what kind of actor?" Human/AI/system actors are
 * distinguished; documents link through to their detail page.
 */
class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        \assert($user instanceof User);

        $action = $request->string('action')->toString();
        $actorType = $request->string('actor_type')->toString();

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->when($actorType !== '', fn ($q) => $q->where('actor_type', $actorType))
            ->where(function ($q) use ($user) {
                // OPD isolation (§36): users only see audit entries for
                // documents of their own OPD; platform staff see everything.
                if ($user->opd_id !== null) {
                    $q->where(function ($q2) use ($user) {
                        $q2->where(function ($q3) use ($user) {
                            $q3->where('subject_type', 'document')
                                ->whereIn('subject_id', function ($q4) use ($user) {
                                    $q4->select('id')->from('documents')->where('opd_id', $user->opd_id);
                                });
                        })->orWhereNull('subject_type');
                    });
                }
            })
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('activity/index', [
            'logs' => [
                'data' => $logs->getCollection()->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'actor_type' => $log->actor_type,
                    'actor_name' => $log->user?->name,
                    'subject_type' => $log->subject_type,
                    'subject_id' => $log->subject_id,
                    'properties' => $log->properties,
                    'created_at' => $log->created_at->toIso8601String(),
                ])->all(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
                'links' => $logs->linkCollection()->all(),
            ],
            'filters' => [
                'action' => $action,
                'actor_type' => $actorType,
            ],
        ]);
    }
}
