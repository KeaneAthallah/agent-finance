<?php

namespace App\Http\Controllers;

use App\Actions\StartDocumentProcessingAction;
use App\Actions\UploadDocumentAction;
use App\Exceptions\DuplicateDocumentException;
use App\Exceptions\MalwareDetectedException;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentExtractionResource;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentAnomaly;
use App\Models\DocumentType;
use App\Models\SihandalMatch;
use App\Models\SihandalSync;
use App\Models\User;
use App\Support\ActorType;
use App\Support\AuditLogger;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    /**
     * Documents index (spec §22 partial, §26): filters + pagination.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        \assert($user instanceof User);

        $status = $request->string('status')->toString();
        $search = $request->string('search')->toString();
        $typeId = $request->integer('document_type_id');
        $fiscalYear = $request->integer('fiscal_year');

        $documents = Document::query()
            ->accessibleBy($user)
            ->with(['documentType:id,code,name', 'opd:id,code,name', 'uploader:id,name'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($typeId > 0, fn ($q) => $q->where('document_type_id', $typeId))
            ->when($fiscalYear > 0, fn ($q) => $q->where('fiscal_year', $fiscalYear))
            ->when($search !== '', fn ($q) => $q->where(fn ($q2) => $q2
                ->where('original_filename', 'like', "%{$search}%")
                ->orWhere('document_number', 'like', "%{$search}%")))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('documents/index', [
            'documents' => DocumentResource::collection($documents),
            'filters' => [
                'status' => $request->string('status')->toString(),
                'document_type_id' => $request->integer('document_type_id') ?: null,
                'fiscal_year' => $request->integer('fiscal_year') ?: null,
                'search' => $request->string('search')->toString(),
            ],
            'documentTypes' => DocumentType::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'can' => [
                'create' => $user->can('create', Document::class),
            ],
        ]);
    }

    /**
     * Store an uploaded document (spec §7). Inertia+JSON aware.
     */
    public function store(StoreDocumentRequest $request, UploadDocumentAction $action): RedirectResponse|JsonResponse
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
            $payload = [
                'message' => 'Duplicate document: an identical file was already uploaded.',
                'existing_document_id' => $exception->existing->id,
            ];

            if ($request->expectsJson()) {
                return response()->json($payload, 409);
            }

            return redirect()
                ->route('documents.index')
                ->withErrors(['file' => $payload['message'].' (existing #'.$exception->existing->id.')']);
        } catch (MalwareDetectedException $exception) {
            // Spec §7: infected files are never persisted.
            if ($request->expectsJson()) {
                return response()->json(
                    ['message' => $exception->getMessage()],
                    422,
                );
            }

            return redirect()
                ->route('documents.index')
                ->withErrors(['file' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return (new DocumentResource($document->loadMissing(['documentType', 'opd', 'uploader'])))
                ->response()
                ->setStatusCode(201);
        }

        return redirect()
            ->route('documents.index')
            ->with('success', 'Document uploaded: '.$document->original_filename);
    }

    /**
     * Document detail (spec §27 skeleton) with pipeline state (§29).
     */
    public function show(Request $request, Document $document): Response
    {
        $this->authorize('view', $document);

        $user = $request->user();

        \assert($user instanceof User);

        $document->load(['documentType', 'opd', 'uploader']);

        $runs = $document->processingRuns()
            ->orderByDesc('id')
            ->get(['stage', 'status', 'error', 'duration_ms', 'version', 'created_at']);

        // Latest extraction pass (any status) with its per-field
        // confidence/evidence rows (spec §12, §13).
        $extraction = $document->extractions()
            ->orderByDesc('version')
            ->with(['fields', 'run:id,provider,model,prompt_name,prompt_version'])
            ->first();

        // Latest validation pass with its anomalies (spec §15) + decision
        // history (spec §14) — the review context on one page.
        $validation = $document->validations()
            ->orderByDesc('version')
            ->first();

        $anomalies = $validation !== null
            ? $validation->anomalies()->orderBy('id')->get()
            : null;

        $reviews = $document->reviews()
            ->orderByDesc('id')
            ->with('reviewer:id,name')
            ->get(['id', 'decision', 'notes', 'reviewer_id', 'created_at']);

        // SIHANDAL candidates (confidence-ordered, §17) and sync
        // history with full request/response (§48).
        $matches = $document->matches()
            ->orderByDesc('confidence')
            ->orderByDesc('id')
            ->with('confirmedBy:id,name')
            ->get(['id', 'version', 'entity_type', 'entity_id', 'sihandal_data', 'confidence', 'status', 'confirmed_by', 'confirmed_at', 'created_at']);

        $syncs = $document->syncs()
            ->orderByDesc('id')
            ->with('user:id,name')
            ->get(['id', 'sihandal_match_id', 'user_id', 'endpoint', 'method', 'request', 'response', 'http_status', 'status', 'error', 'attempts', 'created_at']);

        return Inertia::render('documents/show', [
            'document' => (new DocumentResource($document))->resolve($request),
            'previewUrl' => $this->temporaryPreviewUrl($document),
            'pipeline' => $runs,
            'pages' => $document->pages()
                ->orderBy('page_number')
                ->get(['page_number', 'text_source', 'ocr_confidence', 'processing_status', 'error']),
            'extraction' => $extraction !== null
                ? (new DocumentExtractionResource($extraction))->resolve($request)
                : null,
            'validation' => $validation !== null ? [
                'id' => $validation->id,
                'version' => $validation->version,
                'engine_version' => $validation->engine_version,
                'result' => $validation->result,
                'summary' => $validation->summary,
                'duration_ms' => $validation->duration_ms,
                'created_at' => $validation->created_at->toIso8601String(),
                'anomalies' => $anomalies !== null ? $anomalies->map(fn (DocumentAnomaly $anomaly) => [
                    'id' => $anomaly->id,
                    'type' => $anomaly->type->value,
                    'severity' => $anomaly->severity->value,
                    'status' => $anomaly->status->value,
                    'title' => $anomaly->title,
                    'detail' => $anomaly->detail,
                    'evidence' => $anomaly->evidence,
                ])->all() : [],
            ] : null,
            'reviews' => $reviews->map(fn ($review) => [
                'id' => $review->id,
                'decision' => $review->decision->value,
                'notes' => $review->notes,
                'reviewer' => $review->reviewer !== null
                    ? ['id' => $review->reviewer->id, 'name' => $review->reviewer->name]
                    : null,
                'created_at' => $review->created_at->toIso8601String(),
            ])->all(),
            'can' => [
                'review' => $user->can('review', $document),
            ],
            'sihandal' => [
                'matches' => $matches->map(fn (SihandalMatch $match) => [
                    'id' => $match->id,
                    'version' => $match->version,
                    'entity_type' => $match->entity_type,
                    'entity_id' => $match->entity_id,
                    'sihandal_data' => $match->sihandal_data,
                    'confidence' => $match->confidence,
                    'status' => $match->status->value,
                    'confirmed_by' => $match->confirmedBy !== null
                        ? ['id' => $match->confirmedBy->id, 'name' => $match->confirmedBy->name]
                        : null,
                    'confirmed_at' => $match->confirmed_at?->toIso8601String(),
                    'created_at' => $match->created_at->toIso8601String(),
                ])->all(),
                'syncs' => $syncs->map(fn (SihandalSync $sync) => [
                    'id' => $sync->id,
                    'sihandal_match_id' => $sync->sihandal_match_id,
                    'user' => $sync->user !== null
                        ? ['id' => $sync->user->id, 'name' => $sync->user->name]
                        : null,
                    'endpoint' => $sync->endpoint,
                    'method' => $sync->method,
                    'request' => $sync->request,
                    'response' => $sync->response,
                    'http_status' => $sync->http_status,
                    'status' => $sync->status->value,
                    'error' => $sync->error,
                    'attempts' => $sync->attempts,
                    'created_at' => $sync->created_at->toIso8601String(),
                ])->all(),
            ],
        ]);
    }

    /**
     * Start (or retry) processing without re-uploading (spec §31).
     */
    public function process(Request $request, Document $document, StartDocumentProcessingAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $document);

        $action->execute($document);

        AuditLogger::record(
            action: 'document.reprocess_requested',
            subject: $document,
            actor: $request->user(),
            actorType: ActorType::Human,
            request: $request,
        );

        if ($request->expectsJson()) {
            return response()->json(['queued' => true]);
        }

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'Document queued for processing.');
    }

    /**
     * Delete a document and its stored file.
     */
    public function destroy(Request $request, Document $document): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $document);

        Storage::disk((string) config('agent-finance.documents.disk'))->delete($document->file_path);

        AuditLogger::record(
            action: 'document.deleted',
            subject: $document,
            actor: $request->user(),
            actorType: ActorType::Human,
            request: $request,
            properties: [
                'filename' => $document->original_filename,
                'file_hash' => $document->file_hash,
            ],
        );

        $document->delete();

        if ($request->expectsJson()) {
            return response()->json(['deleted' => true]);
        }

        return redirect()->route('documents.index')->with('success', 'Document deleted.');
    }

    /**
     * Signed, expiring download (spec §8): files never sit in the public root.
     */
    public function download(Request $request, Document $document): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('view', $document);

        AuditLogger::record(
            action: 'document.downloaded',
            subject: $document,
            actor: $request->user(),
            actorType: ActorType::Human,
            request: $request,
        );

        return $this->fileResponse($document);
    }

    private function fileResponse(Document $document, bool $inline = false): \Symfony\Component\HttpFoundation\Response
    {
        $disk = Storage::disk((string) config('agent-finance.documents.disk'));

        // S3-compatible disks offer temporary signed URLs; the local disk
        // streams through the authorized route instead.
        $temporaryUrl = $disk instanceof AwsS3V3Adapter
            ? $disk->temporaryUrl($document->file_path, now()->addMinutes(10))
            : null;

        if (is_string($temporaryUrl)) {
            return redirect()->away($temporaryUrl);
        }

        return $inline
            ? $disk->response($document->file_path, $document->original_filename)
            : $disk->download($document->file_path, $document->original_filename);
    }

    private function temporaryPreviewUrl(Document $document): string
    {
        return route('documents.download', $document);
    }
}
