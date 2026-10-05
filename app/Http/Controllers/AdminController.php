<?php

namespace App\Http\Controllers;

use App\Models\AiPrompt;
use App\Models\DocumentAnomaly;
use App\Models\DocumentType;
use App\Models\Opd;
use App\Models\Role;
use App\Models\SihandalSync;
use App\Models\User;
use App\Support\ActorType;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administration (spec §44): the platform control surface —
 * users, OPDs, the document-type catalog and the versioned
 * AI prompt registry. Page access is admin-only (route
 * middleware); every mutation is super-admin-only and
 * audit-logged (§35). Prompt rows are immutable (§33):
 * editing creates a new version, activation moves the
 * is_active flag, existing rows are never rewritten.
 */
final class AdminController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        \assert($user instanceof User);

        $prompts = AiPrompt::query()
            ->orderBy('name')
            ->orderByDesc('id')
            ->get()
            ->groupBy('name');

        return Inertia::render('admin/index', [
            'users' => User::query()
                ->with(['role:id,slug,name', 'opd:id,code,name'])
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role_id', 'opd_id', 'is_active']),
            'roles' => Role::query()->orderBy('name')->get(['id', 'slug', 'name']),
            'opds' => Opd::query()->orderBy('name')->get(['id', 'code', 'name', 'is_active']),
            'documentTypes' => DocumentType::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'description', 'is_active']),
            'prompts' => $prompts
                ->map(fn ($versions) => $versions->map(fn (AiPrompt $prompt): array => [
                    'id' => $prompt->id,
                    'name' => $prompt->name,
                    'version' => $prompt->version,
                    'system_prompt' => $prompt->system_prompt,
                    'model' => $prompt->model,
                    'temperature' => (string) $prompt->temperature,
                    'is_active' => $prompt->is_active,
                    'created_at' => $prompt->created_at->toISOString(),
                ])->values()->all())
                ->all(),
            'health' => $this->health(),
            'can' => [
                'manage' => $user->isSuperAdmin(),
            ],
        ]);
    }

    /**
     * Update a user's role, OPD or active flag (super admin
     * only — user administration is a platform concern).
     */
    public function updateUser(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $this->requireSuperAdmin($request);

        $validated = $request->validate([
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'opd_id' => ['nullable', 'integer', 'exists:opds,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user->update($validated);

        AuditLogger::record(
            action: 'user.updated',
            subject: $user,
            actor: $request->user(),
            actorType: ActorType::Human,
            request: $request,
            properties: $validated,
        );

        return $this->respond($request, ['user' => $user->only('id', 'name', 'is_active')]);
    }

    /**
     * Create an OPD (super admin only).
     */
    public function storeOpd(Request $request): RedirectResponse|JsonResponse
    {
        $this->requireSuperAdmin($request);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:opds,code'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $opd = Opd::query()->create($validated + ['is_active' => true]);

        AuditLogger::record(
            action: 'opd.created',
            subject: $opd,
            actor: $request->user(),
            actorType: ActorType::Human,
            request: $request,
        );

        return $this->respond($request, ['opd' => $opd->only('id', 'code', 'name')]);
    }

    /**
     * Rename or deactivate an OPD (super admin only).
     */
    public function updateOpd(Request $request, Opd $opd): RedirectResponse|JsonResponse
    {
        $this->requireSuperAdmin($request);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $opd->update($validated);

        AuditLogger::record(
            action: 'opd.updated',
            subject: $opd,
            actor: $request->user(),
            actorType: ActorType::Human,
            request: $request,
            properties: $validated,
        );

        return $this->respond($request, ['opd' => $opd->only('id', 'code', 'name', 'is_active')]);
    }

    /**
     * Toggle a document type's active flag (super admin only).
     * Schemas stay code-owned (§6) — the catalog row is admin data.
     */
    public function updateDocumentType(Request $request, DocumentType $documentType): RedirectResponse|JsonResponse
    {
        $this->requireSuperAdmin($request);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $documentType->update($validated);

        AuditLogger::record(
            action: 'document_type.updated',
            subject: $documentType,
            actor: $request->user(),
            actorType: ActorType::Human,
            request: $request,
            properties: $validated,
        );

        return $this->respond($request, ['documentType' => $documentType->only('id', 'code', 'is_active')]);
    }

    /**
     * Create a new prompt version (super admin only, §33).
     * Version rows are immutable — this inserts a new row and
     * makes it the active version of its name.
     */
    public function storePrompt(Request $request): RedirectResponse|JsonResponse
    {
        $this->requireSuperAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'version' => [
                'required',
                'string',
                'max:32',
                Rule::unique('ai_prompts')->where(fn ($query) => $query->where('name', $request->input('name'))),
            ],
            'system_prompt' => ['required', 'string'],
            'model' => ['nullable', 'string', 'max:128'],
            'temperature' => ['nullable', 'numeric', 'between:0,2'],
        ]);

        DB::transaction(function () use ($validated, $request): void {
            $prompt = AiPrompt::query()->create([
                'name' => $validated['name'],
                'version' => $validated['version'],
                'system_prompt' => $validated['system_prompt'],
                'model' => $validated['model'] ?? null,
                'temperature' => $validated['temperature'] ?? 0.1,
                'is_active' => true,
            ]);

            // One active version per name: the new version
            // replaces the previous one.
            AiPrompt::query()
                ->where('name', $validated['name'])
                ->whereKeyNot($prompt->id)
                ->update(['is_active' => false]);

            AuditLogger::record(
                action: 'prompt.created',
                subject: $prompt,
                actor: $request->user(),
                actorType: ActorType::Human,
                request: $request,
                properties: ['name' => $validated['name'], 'version' => $validated['version']],
            );
        });

        return $this->respond($request, ['created' => true]);
    }

    /**
     * Activate an existing prompt version (super admin only).
     * Moves the is_active flag; no row is rewritten.
     */
    public function activatePrompt(Request $request, AiPrompt $prompt): RedirectResponse|JsonResponse
    {
        $this->requireSuperAdmin($request);

        DB::transaction(function () use ($prompt, $request): void {
            AiPrompt::query()
                ->where('name', $prompt->name)
                ->whereKeyNot($prompt->id)
                ->update(['is_active' => false]);

            $prompt->update(['is_active' => true]);

            AuditLogger::record(
                action: 'prompt.activated',
                subject: $prompt,
                actor: $request->user(),
                actorType: ActorType::Human,
                request: $request,
                properties: ['name' => $prompt->name, 'version' => $prompt->version],
            );
        });

        return $this->respond($request, ['activated' => true]);
    }

    /**
     * Mutations on the administration surface are
     * super-admin-only (§36, §44).
     */
    private function requireSuperAdmin(Request $request): void
    {
        $user = $request->user();

        \assert($user instanceof User);

        if (! $user->isSuperAdmin()) {
            abort(403, 'Administration mutations require the super admin role.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function respond(Request $request, array $payload): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return redirect()
            ->route('admin.index')
            ->with('success', 'Change recorded.');
    }

    /**
     * Read-only platform health snapshot (§51): provider
     * configuration, queue, storage and open work.
     *
     * @return array<string, mixed>
     */
    private function health(): array
    {
        $provider = (string) config('agent-finance.ai.provider');
        $providers = config('agent-finance.ai.providers', []);
        $key = $providers[$provider]['key'] ?? null;

        return [
            'ai_provider' => $provider,
            'ai_key_configured' => filled($key),
            'embedding_model' => (string) config('agent-finance.ai.embedding_model'),
            'ocr_driver' => (string) config('agent-finance.ocr.driver'),
            'sihandal' => filled(config('agent-finance.sihandal.base_url')) ? 'api' : 'fake',
            'queue' => (string) config('queue.default'),
            'open_anomalies' => DocumentAnomaly::query()->open()->count(),
            'pending_syncs' => SihandalSync::query()->where('status', 'pending')->count(),
        ];
    }
}
