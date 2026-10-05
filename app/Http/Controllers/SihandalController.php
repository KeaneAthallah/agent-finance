<?php

namespace App\Http\Controllers;

use App\Enums\SihandalMatchStatus;
use App\Enums\SihandalSyncStatus;
use App\Models\Document;
use App\Models\SihandalMatch;
use App\Models\SihandalSync;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SIHANDAL section (spec §44): platform-wide view of the
 * integration — pending match candidates awaiting human
 * confirmation, and the sync history with the system of
 * record. OPD-scoped exactly like documents (§36).
 */
class SihandalController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        \assert($user instanceof User);

        $accessible = Document::query()->accessibleBy($user);

        $pendingMatches = SihandalMatch::query()
            ->whereIn('document_id', $accessible->select('id'))
            ->where('status', SihandalMatchStatus::Pending->value)
            ->with(['document:id,original_filename,document_number,status', 'document.opd:id,code,name'])
            ->orderByDesc('confidence')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $syncs = SihandalSync::query()
            ->whereIn('document_id', Document::query()->accessibleBy($user)->select('id'))
            ->with(['document:id,original_filename,document_number', 'user:id,name'])
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'syncs_page')
            ->withQueryString();

        $counts = [
            'pending_matches' => SihandalMatch::query()
                ->whereIn('document_id', $accessible->select('id'))
                ->where('status', SihandalMatchStatus::Pending->value)
                ->count(),
            'pending_syncs' => SihandalSync::query()
                ->whereIn('document_id', $accessible->select('id'))
                ->where('status', SihandalSyncStatus::Pending->value)
                ->count(),
            'failed_syncs' => SihandalSync::query()
                ->whereIn('document_id', $accessible->select('id'))
                ->where('status', SihandalSyncStatus::Failed->value)
                ->count(),
            'successful_syncs' => SihandalSync::query()
                ->whereIn('document_id', $accessible->select('id'))
                ->where('status', SihandalSyncStatus::Success->value)
                ->count(),
        ];

        return Inertia::render('sihandal/index', [
            'counts' => $counts,
            'pendingMatches' => $pendingMatches,
            'syncs' => $syncs,
            'can' => [
                'review' => $user->can('review', Document::class),
            ],
        ]);
    }
}
