# Agent Finance — Implementation Plan

Grounded in the actual repository state (inspected before writing any code,
per §63 and §65). This plan is the working backlog; `docs/ARCHITECTURE.md`
describes the target architecture it implements.

---

## 0. Repository Baseline (what actually exists)

The repository is the official **Laravel blank React starter kit**:

- **Backend**: Laravel 13 (PHP ^8.3), Inertia Laravel v3, Wayfinder v0.1,
  Pest v5 + Pest Laravel plugin, Larastan (level 7), Pint (laravel preset),
  Laravel Boost, Sail (dev dependency), no auth scaffolding, no API routes.
- **Frontend**: React 19 + TypeScript, Vite 8 (vite-plus), Tailwind CSS v4
  (via `@tailwindcss/vite`), React Compiler (babel preset), `@inertiajs/react`
  v3, `clsx` + `tailwind-merge` (`cn()` helper), Wayfinder Vite plugin
  generating typed routes/actions into `resources/js/routes|actions`.
- **Infra**: SQLite by default (`DB_CONNECTION=sqlite`), database queue/cache/
  sessions, no Docker setup, no Node-only frontend (single Vite app served by
  Laravel), GitHub Actions CI running `composer ci:check`
  (pint + phpstan + `artisan test`, plus `npm run check` / `types:check`).
- **Database**: 3 base migrations (users + password_reset_tokens + sessions,
  cache, jobs). 1 factory (User). 4 app files (Controller, User, Provider,
  HandleInertiaRequests).

### Phase 1 decisions (recorded)

| Decision              | Choice                                                                                                     | Rationale                                                                                                                                                                                                            |
| --------------------- | ---------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Frontend architecture | **Inertia + React** (not decoupled SPA + React Router)                                                     | The starter kit ships Inertia v3 + session auth + Wayfinder; reusing it keeps CSRF/session auth, typed routes, and SSR-ready builds for free. Spec's TanStack Query can still be layered in for polling panes later. |
| Database              | **MySQL 8**                                                                                                | Per spec §2 and SIHANDAL V2 alignment. Docker Compose provides MySQL; tests keep SQLite in-memory for speed.                                                                                                         |
| UI stack              | **shadcn/ui-style primitives hand-rolled** on Tailwind v4 (no runtime deps beyond `clsx`/`tailwind-merge`) | Matches spec's "shadcn/ui **or an equivalent high-quality component system**" without adding dependency risk in Phase 1. Can migrate to shadcn CLI later — components live in `resources/js/components/ui`.          |
| Auth surface          | Session-based auth via Inertia forms + Wayfinder actions (Sanctum reserved for `/api/v1` machine clients)  | Consistent with decision #1; Sanctum ships with Laravel 13 when needed.                                                                                                                                              |

### Deviations from spec text (intentional)

- **React Router** → not used; Inertia provides routing/navigation
  (recorded above).
- **Laravel Sanctum** → deferred to Phase 6 (first `/api/v1` consumers);
  session auth covers the Inertia frontend now.
- **TanStack Query** → deferred until a polling-heavy need exists (pipeline
  progress, Phase 3+) to avoid two competing data layers in Phase 1.
- **MinIO** → optional compose profile, not required for Phase 1.

---

## 1. Phase Plan & Status

Status legend: ✅ done · 🚧 in progress · ⬜ not started

### Phase 1 — Foundation 🚧 (this change)

- [x] `docs/ARCHITECTURE.md`, `docs/IMPLEMENTATION_PLAN.md`
- [x] Docker-first setup: `docker-compose.yml` (app, nginx, mysql, redis,
      queue, scheduler, frontend), PHP-FPM Dockerfile, nginx conf,
      container entrypoint, fully documented `.env.example`
- [x] `config/agent-finance.php` — documents (types, max size, allowed
      mimes), AI (provider, models, key via env), OCR, SIHANDAL (base URL,
      key, timeout, cache TTL), validation thresholds
- [x] Migrations: `roles`, `opds`, `document_types`; `users` += `role_id`,
      `opd_id`, `is_active`
- [x] Models + enums: `Role`, `Opd`, `DocumentType`, `DocumentStatus`,
      `UserRole` + updated `User`
- [x] Seeders: roles, OPDs (sample), document types (DPA, RKA, SPM, SP2D,
      ... configurable list), admin user
- [x] Auth: login page + `LoginRequest` + controller + logout; `auth`
      middleware on the app
- [x] Authorization: role middleware (`role:super_admin`), OPD scoping
      helpers, `DocumentPolicy` stub
- [x] Dashboard page (operational, charts-free) + placeholder sections
      (Documents, Review, Validation, Anomalies, AI Assistant, SIHANDAL,
      Reports, Activity, Settings, Administration) with role-aware sidebar
- [x] Tests: auth (login/logout/validation), role authorization,
      OPD scoping of users, seeder integrity
- [x] Quality gates: pint, phpstan level 7, pest, `tsc --noEmit` all green

### Phase 2 — Document Upload & Storage ✅

- [x] `documents` migration (hash unique, OPD FK, lifecycle indexes) + model
      with `accessibleBy` OPD scope + factory
- [x] Private `documents` disk (`serve => false` — never public), upload
      action with SHA-256 hashing, duplicate rejection (409 JSON / UI error),
      PDF page counting, `DocumentUploaded` event
- [x] `FileSignature` rule (magic-number validation, blocks renamed
      executables), size/extension/MIME validation via `StoreDocumentRequest`
- [x] `DocumentPolicy` (view/create/delete with OPD isolation), authorized
      download route (S3 signed URL when available, authorized stream
      otherwise)
- [x] Documents index page (upload form, status/type/search filters,
      pagination) + detail page skeleton with metadata and preview link
- [x] Tests: upload validation, duplicate hash rejection (UI + 409 API),
      signature enforcement, OPD isolation (index/view/download/delete),
      viewer restrictions — 20 new tests, 49 total

### Phase 3 — Queue, Text Extraction & Classification ✅

- [x] `document_pages` + `document_processing_runs` migrations and models
      (per-page text/OCR state; stage-level observability with duration,
      errors, versions)
- [x] `smalot/pdfparser` embedded-text extraction (PDF/CSV/DOCX/XLSX)
      via `TextExtractor`; `PdfPageRenderer` (poppler `pdftoppm`) renders
      scanned pages to PNG for OCR input
- [x] Job chain: `StartDocumentProcessingAction` → `ProcessDocumentJob` →
      `ExtractTextJob` → `ClassifyDocumentJob`, with version guards against
      stale retries (§31), explicit failure states (§5), and backoff/retry
- [x] Auto-start after upload via queued `QueueDocumentProcessing` listener
      (failure-safe — never breaks the upload response); manual
      `POST /documents/{id}/process` retry endpoint
- [x] AI classification against the document_types catalog via the
      `AIProviderInterface` (fake driver in tests, phpunit.xml pinned)
- [x] Dashboard counters now computed live from `documents` (OPD-scoped)
- [x] Pipeline progress UI on the detail page (real run states, durations,
      errors, retry button, per-page source/confidence table)
- [x] Docker image: poppler-utils, tesseract-ocr + ind/eng traineddata
- [x] Tests: full sync-pipeline run, page extraction content, corruption
      failure state, stale-version guard, retry-without-reupload, pipeline
      UI payload, classification — 8 new tests, 57 total

### Phase 4 — AI Extraction ✅

- [x] `AIProviderInterface` + `OpenAIProvider` (+ env-selected fake for dev;
      anthropic/google placeholders fail fast until implemented)
- [x] `ai_prompts`, `ai_runs`, `ai_usage` migrations; `PromptRegistry`
      resolves the active prompt version with a built-in fallback (recorded
      on every run); prompt identity denormalized onto `ai_runs` (§33)
- [x] `document_extractions` (versioned payload, status, confidence) +
      `document_extraction_fields` (value + confidence + evidence:
      page, bbox, source text); history kept per processing version (§38)
- [x] `ExtractionSchemaRegistry`: typed per-type field schemas (SPM, SP2D,
      SPP, invoice/kwitansi/faktur, bukti_transfer, DPA/RKA, generic
      fallback) rendered to JSON schema for the provider
- [x] `ExtractFinancialDataJob` dispatched after `ClassifyDocumentJob`:
      stale-version guard, ai_run + ai_usage rows, explicit failure state
      (`ai_failed`), transactional field persistence
- [x] Providers return per-field confidence/evidence; `FakeAIProvider` is
      schema-driven and deterministic (regex-based, page evidence)
- [x] Detail page: extraction pane with overall/field confidence badges,
      evidence jump-to-location, run metadata (provider/model/prompt)
- [x] Tests: sync-pipeline extraction, per-field confidence/evidence,
      ai_run/ai_usage rows, prompt version recording, schema registry
      fallback, version history, provider-failure state, stale-version
      guard, UI payload, token usage — 10 new tests, 67 total

### Phase 5 — Review, Validation, Audit ✅

- [x] `document_reviews`, `document_validations`, `document_anomalies`,
      `audit_logs` migrations
- [x] Validation engine (rule classes): TOTAL_MISMATCH, FISCAL_YEAR_MISMATCH,
      MISSING_DOCUMENT_NUMBER, LOW_CONFIDENCE, duplicate scan
- [x] Review queue page; approve/reject/request re-analysis flows; every manual
      correction audited (actor human vs AI distinguished)
- [x] Auto-approval of clean, confident extractions; idempotent re-validation;
      system-actor audit entries for validation routing

### Phase 6 — SIHANDAL Integration ✅

- [x] `SihandalApiInterface` + `SihandalClient` (cached reads,
      uncached `syncDocument` write) + `FakeSihandalClient`
      (deterministic belanja fixtures, offline `syncDocument`)
- [x] `sihandal_matches` + `sihandal_syncs` migrations +
      `documents.synced_at`; `MatchSihandalDataJob` dispatched
      after `ValidateDocumentJob` (same version, advisory —
      unreachable SIHANDAL never blocks the pipeline, §50)
- [x] `MatchingService`: explainable 0–100 scoring (number 50 /
      amount 30 / fiscal year 10 / OPD 10), pending candidates
      replaced per version, confirmed/rejected kept as history
- [x] `SyncToSihandalAction`: full request/response persisted per
      attempt; connection failure → pending + retryable (same
      payload); API error → `sync_failed` + audited; confirm/
      reject/retry flows with review ability + OPD isolation
- [x] SIHANDAL section page (counts, confidence-ordered
      candidates, sync history) + detail-page SIHANDAL pane
- [x] Sanctum-protected `/api/v1` for machine clients (list,
      show, upload, review, match candidates, confirm/sync) —
      same OPD isolation and policies as the UI (§43)
- [x] `phpunit.xml` hardening: `<server>` + forced `<env>` pins
      so ambient OS env never leaks into tests
- [x] Tests: matching order/idempotency/history, failure-safe
      matching, confirm/reject/retry, OPD isolation, API
      clients, UI payloads — 19 new tests, 110 total

### Phase 7 — AI Assistant, Tools, RAG ✅

- Tool layer (`get_budget_summary`, `search_documents`,
  `compare_document_with_sihandal`, `get_sihandal_data`
  — all SIHANDAL list resources: opds, programs,
  kegiatan, sub_kegiatan, belanja, rekenings,
  sumber_dana, penerimaan, pengeluaran,
  tahun_anggaran —,
  `validate_document`) — read-only, OPD-scoped
- Chat page with FACT/CALCULATION/AI INTERPRETATION/WARNING labels;
  tool errors surface as WARNING, never leak out-of-scope IDs
- `document_chunks` + embeddings (whitelisted content only —
  completed extractions; metadata filters: OPD, fiscal year,
  type) + hybrid search (SQL + cosine similarity in PHP)
- `GenerateEmbeddingsJob` after SIHANDAL matching (advisory, §50);
  chat runs recorded on `ai_runs`/`ai_usage`

### Phase 8 — Anomalies & Financial Analysis ✅

- Extensible anomaly engine: rule registry is the extension
  point (new rules register in `AppServiceProvider`, never
  hardcoded); severity/status/resolution lifecycle with
  `resolved_at`/`resolved_by` and audit trail
- Duplicate detection: same document number (critical), same
  payee+amount+date composite (high), semantic content
  similarity via embeddings ≥ 0.95 (high) — all OPD-scoped
- Anomaly workspace (`/anomalies`): open-indicator queue
  with severity/type filters, resolve/dismiss decisions
  (review ability, §36), document links to the SIHANDAL
  comparison view with evidence on both sides
- `KnowledgeContent` shared by the embedding job and the
  semantic rule (same whitelisted format); cosine similarity
  extracted to `Support\Similarity`
- FakeAIProvider embeddings are bag-of-words (shared
  vocabulary → similar vectors), deterministic for tests

### Phase 9 — Reports, Admin, Cost ✅

- Reports (`/reports`): documents processed (status,
  fiscal-year trend, top types), extraction confidence
  distribution, review workload (open anomalies by
  severity, pending SIHANDAL confirmations), sync health,
  AI token/cost dashboard (totals + per-model breakdown)
- Reports are platform-wide for super admins, OPD-scoped
  for everyone else (§36) — document metrics, confidence,
  anomalies, syncs and document-tied AI usage
- Admin (`/administration`): users (role/OPD/active
  editing), OPDs (create/rename/deactivate), document-type
  activation, AI prompt versions, system health
- Settings (`/settings`): read-only effective
  configuration (AI, OCR, SIHANDAL, validation,
  documents, queue) — no write path
- Prompt immutability enforced (§33): storePrompt inserts
  a new version row and moves is_active; activatePrompt
  only flips the flag — existing rows are never rewritten
- Admin mutations are super-admin-only and audit-logged
  (user.updated, opd.created/updated, document_type.updated,
  prompt.created/activated)
- DocumentExtractionField::extraction now declares the
  real FK (document_extraction_id); Opd casts is_active

### Phase 10 — Hardening ✅

- Vitest + RTL component tests (ui primitives, AppShell role
  navigation — 10 tests); Playwright E2E smoke (login, dashboard,
  rejection) with `npm run test:e2e`
- Security pass (§35): SecurityHeaders middleware (strict CSP,
  nosniff, DENY framing, referrer/permissions policies) on the
  web group; login throttled 5,1 and AI chat 10,1; malware-scan
  hook (§7) — MalwareScannerInterface with Fake/ClamAV drivers,
  uploads scanned before persistence, infected files rejected 422
  and never stored; secrets audit test (.env gitignored, no
  private keys tracked)
- Production Docker target already in place; added .dockerignore
  build-context hygiene
- Performance pass: composite indexes verified (documents
  (opd_id,status)/(document_type_id,fiscal_year)/(fiscal_year,
  created_at), reviews/validations/anomalies/audit_logs); all
  index pages paginate (15–30/page); queue worker tuned
  (--tries, --backoff, --timeout, --memory, --sleep)

---

## 2. Verification Loop (after every phase)

1. `composer lint` (pint) · 2. `composer types:check` (phpstan L7)
2. `composer test` (pest) · 4. `npm run types:check` + `npm run check`
3. `docker compose up -d` smoke test · 6. Manual API/UI smoke
4. Report changes + remaining work in this file

## 3. Open Questions (block later phases, not Phase 1)

- SIHANDAL V2 API contract: is there a staging base URL + sandbox key?
  (Blocks Phase 6; `FakeSihandalClient` unblocks everything else.)
- AI vendor preference & data-privacy constraints for government documents
  (may force `LocalAIProvider` as default) — blocks Phase 4 final choice,
  not the abstraction.
- Retention policy for documents/AI outputs (§58) — needed before Phase 9.
