<?php

namespace App\Http\Controllers;

use App\Actions\ReviewDocumentAction;
use App\Enums\DocumentStatus;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Human review workspace (spec §14, §22): the queue of documents awaiting a
 * decision (OPD-scoped, oldest first, with anomaly + confidence context) and
 * the approve/reject/re-analysis endpoints. Viewers can look but the decision
 * buttons require the review ability (spec §36).
 */
class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        \assert($user instanceof User);

        $documents = Document::query()
            ->accessibleBy($user)
            ->where('status', DocumentStatus::ReviewRequired)
            ->with(['documentType:id,code,name', 'opd:id,code,name', 'uploader:id,name'])
            ->withCount(['anomalies as open_anomalies_count' => fn ($q) => $q->open()])
            ->withMax('reviews as last_reviewed_at', 'created_at')
            ->orderBy('validated_at')
            ->orderBy('created_at')
            ->paginate(15)
            ->withQueryString();

        // Severity of the worst open anomaly per document, for queue sorting
        // hints in the UI — one grouped query, no N+1 (§52).
        $pageDocuments = $documents->getCollection();

        $severities = $pageDocuments->isNotEmpty()
            ? DB::table('document_anomalies')
                ->whereIn('document_id', $pageDocuments->modelKeys())
                ->where('status', 'open')
                ->groupBy('document_id')
                ->selectRaw('document_id, min(severity) as worst_severity')
                ->pluck('worst_severity', 'document_id')
            : collect();

        $openAnomalies = $pageDocuments
            ->mapWithKeys(fn (Document $d) => [$d->id => (int) $d->getAttribute('open_anomalies_count')])
            ->all();

        return Inertia::render('review/index', [
            'documents' => DocumentResource::collection($documents),
            'openAnomalies' => $openAnomalies,
            'worstSeverities' => $severities->all(),
            'can' => [
                'review' => $user->can('review', Document::class),
            ],
        ]);
    }

    public function store(StoreReviewRequest $request, Document $document, ReviewDocumentAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('review', $document);

        $user = $request->user();

        \assert($user instanceof User);

        $extraction = $document->extractions()
            ->where('status', 'completed')
            ->orderByDesc('version')
            ->first();

        $action->execute(
            document: $document,
            reviewer: $user,
            decision: $request->validated('decision'),
            notes: $request->validated('notes'),
            extractionVersion: $extraction?->version,
            extractionSnapshot: $extraction?->payload,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'reviewed' => true,
                'status' => $document->refresh()->status->value,
            ]);
        }

        return redirect()
            ->route('review.index')
            ->with('success', 'Review recorded for '.$document->original_filename.'.');
    }
}
