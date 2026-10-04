<?php

namespace App\Http\Controllers;

use App\Actions\SyncToSihandalAction;
use App\Enums\SihandalMatchStatus;
use App\Enums\SihandalSyncStatus;
use App\Models\Document;
use App\Models\SihandalMatch;
use App\Models\SihandalSync;
use App\Models\User;
use App\Support\ActorType;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Human decisions on SIHANDAL candidates (spec §17, §48):
 * confirm (which also performs the sync through the API —
 * the only write path to the system of record), reject, and
 * retry of a pending/failed sync. All require the review
 * ability and OPD access; every decision is audited (§35).
 */
class SihandalMatchController extends Controller
{
    public function confirm(
        Request $request,
        Document $document,
        SihandalMatch $match,
        SyncToSihandalAction $action,
    ): RedirectResponse {
        $this->authorize('review', $document);

        if ($match->document_id !== $document->id) {
            abort(404);
        }

        $user = $request->user();

        \assert($user instanceof User);

        try {
            $sync = $action->execute($document, $match, $user, $request);
        } catch (\LogicException $exception) {
            return back()->withErrors(['sihandal' => $exception->getMessage()]);
        }

        if ($sync->status === SihandalSyncStatus::Success) {
            return back()->with('success', 'Synced to SIHANDAL: '.$document->original_filename.'.');
        }

        return back()->withErrors(['sihandal' => 'SIHANDAL is unreachable. The sync stays pending and can be retried.']);
    }

    public function reject(
        Request $request,
        Document $document,
        SihandalMatch $match,
    ): RedirectResponse {
        $this->authorize('review', $document);

        if ($match->document_id !== $document->id) {
            abort(404);
        }

        if ($match->status !== SihandalMatchStatus::Pending) {
            return back()->withErrors(['sihandal' => 'The match candidate has already been decided.']);
        }

        $user = $request->user();

        \assert($user instanceof User);

        $match->update([
            'status' => SihandalMatchStatus::Rejected->value,
            'confirmed_by' => $user->id,
            'confirmed_at' => now(),
        ]);

        AuditLogger::record(
            action: 'sihandal.match_rejected',
            subject: $document,
            actor: $user,
            actorType: ActorType::Human,
            request: $request,
            properties: [
                'sihandal_match_id' => $match->id,
                'entity_type' => $match->entity_type,
                'entity_id' => $match->entity_id,
                'confidence' => $match->confidence,
            ],
        );

        return back()->with('success', 'Match candidate rejected.');
    }

    public function retry(
        Request $request,
        Document $document,
        SihandalSync $sync,
        SyncToSihandalAction $action,
    ): RedirectResponse {
        $this->authorize('review', $document);

        if ($sync->document_id !== $document->id) {
            abort(404);
        }

        $user = $request->user();

        \assert($user instanceof User);

        try {
            $sync = $action->retry($sync, $user, $request);
        } catch (\LogicException $exception) {
            return back()->withErrors(['sihandal' => $exception->getMessage()]);
        }

        if ($sync->status === SihandalSyncStatus::Success) {
            return back()->with('success', 'Sync to SIHANDAL succeeded on retry.');
        }

        return back()->withErrors(['sihandal' => 'SIHANDAL is still unreachable. The sync remains pending.']);
    }
}
