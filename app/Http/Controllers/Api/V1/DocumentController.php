<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ReviewDocumentAction;
use App\Actions\SyncToSihandalAction;
use App\Actions\UploadDocumentAction;
use App\Enums\SihandalMatchStatus;
use App\Enums\SihandalSyncStatus;
use App\Exceptions\DuplicateDocumentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\SihandalMatch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Sanctum-protected API for machine clients (spec §43): the
 * same capabilities the UI has — upload, list, show, review,
 * and the SIHANDAL confirm/sync path — over a token API.
 * OPD isolation still applies server-side (§36).
 */
final class DocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        \assert($user instanceof User);

        $documents = Document::query()
            ->accessibleBy($user)
            ->with(['documentType:id,code,name', 'opd:id,code,name', 'uploader:id,name'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return DocumentResource::collection($documents);
    }

    public function show(Request $request, Document $document): DocumentResource
    {
        $this->authorize('view', $document);

        return new DocumentResource($document->load(['documentType', 'opd', 'uploader']));
    }

    public function store(StoreDocumentRequest $request, UploadDocumentAction $action): JsonResponse
    {
        $user = $request->user();

        \assert($user instanceof User);

        $validated = $request->validated();

        try {
            $document = $action->execute(
                file: $request->file('file') ?? throw new \InvalidArgumentException('File is required.'),
                uploader: $user,
                documentType: isset($validated['document_type_id'])
                    ? DocumentType::query()->findOrFail((int) $validated['document_type_id'])
                    : null,
                fiscalYear: isset($validated['fiscal_year']) ? (int) $validated['fiscal_year'] : null,
                request: $request,
            );
        } catch (DuplicateDocumentException $exception) {
            return response()->json([
                'message' => 'Duplicate document: an identical file was already uploaded.',
                'existing_document_id' => $exception->existing->id,
            ], 409);
        }

        return (new DocumentResource($document->loadMissing(['documentType', 'opd', 'uploader'])))
            ->response()
            ->setStatusCode(201);
    }

    public function review(
        StoreReviewRequest $request,
        Document $document,
        ReviewDocumentAction $action,
    ): JsonResponse {
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

        return response()->json([
            'reviewed' => true,
            'status' => $document->refresh()->status->value,
        ]);
    }

    /**
     * Match candidates for a document, ordered by confidence (§17).
     */
    public function matches(Request $request, Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $matches = $document->matches()
            ->where('status', SihandalMatchStatus::Pending->value)
            ->orderByDesc('confidence')
            ->orderByDesc('id')
            ->get(['id', 'version', 'entity_type', 'entity_id', 'sihandal_data', 'confidence', 'status', 'created_at']);

        return response()->json([
            'matches' => $matches->map(fn (SihandalMatch $match) => [
                'id' => $match->id,
                'version' => $match->version,
                'entity_type' => $match->entity_type,
                'entity_id' => $match->entity_id,
                'sihandal_data' => $match->sihandal_data,
                'confidence' => $match->confidence,
                'status' => $match->status->value,
                'created_at' => $match->created_at->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * Confirm a candidate and sync the document to SIHANDAL (§17, §48).
     */
    public function confirmMatch(
        Request $request,
        Document $document,
        SihandalMatch $match,
        SyncToSihandalAction $action,
    ): JsonResponse {
        $this->authorize('review', $document);

        if ($match->document_id !== $document->id) {
            abort(404);
        }

        $user = $request->user();

        \assert($user instanceof User);

        try {
            $sync = $action->execute($document, $match, $user, $request);
        } catch (\LogicException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'synced' => $sync->status === SihandalSyncStatus::Success,
            'sync' => [
                'id' => $sync->id,
                'status' => $sync->status->value,
                'endpoint' => $sync->endpoint,
                'http_status' => $sync->http_status,
                'response' => $sync->response,
                'attempts' => $sync->attempts,
                'error' => $sync->error,
            ],
            'document_status' => $document->refresh()->status->value,
        ], $sync->status === SihandalSyncStatus::Success ? 200 : 503);
    }
}
