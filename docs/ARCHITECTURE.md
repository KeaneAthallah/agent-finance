# Agent Finance — Architecture

> AI-Powered Government Financial Document Intelligence Platform
> Intelligence layer for **SIHANDAL V2 — Sistem Informasi Keuangan Daerah**

This document describes the architecture of Agent Finance as it is being built
on top of this repository: a **Laravel 13** application with an **Inertia v3 +
React 19** frontend (decided during Phase 1; see `docs/IMPLEMENTATION_PLAN.md`
for the decision record).

---

## 1. Product Principle

Agent Finance is **not** a PDF uploader with a chatbot. It is an
**AI financial document analyst**. Its core loop:

```
UPLOAD → UNDERSTAND → EXTRACT → SHOW EVIDENCE → VALIDATE
→ MATCH WITH SIHANDAL → HUMAN REVIEW → CONFIRM → SYNC → ANALYZE → AUDIT
```

Two absolute rules govern every design decision:

1. **AI recommends, humans decide, business systems act.**
   AI output never writes to financial state directly. It flows through
   structured extraction → validation → human review → explicit confirmation →
   authorized API action.
2. **SIHANDAL V2 remains the financial system of record.**
   Agent Finance never connects to the SIHANDAL database. All financial
   master/transaction data is read (and, after human confirmation, written)
   exclusively through the SIHANDAL REST API.

---

## 2. High-Level System Architecture

```
                      Inertia + React (SPA-feel UI)
                                   │
                                   ▼
                        Laravel 13 Application
                    (HTTP controllers, Inertia pages,
                     /api/v1 JSON API, Policies)
                                   │
        ┌──────────────┬───────────┼───────────────┬──────────────┐
        ▼              ▼           ▼               ▼              ▼
     MySQL          Redis       Private         Queue         Scheduler
   (state/DB)     (cache/     Object Storage   (Laravel       (cron, retention)
                   locks)     (documents)       Queue)
                                                     │
                                          ┌──────────┴──────────┐
                                          ▼                     ▼
                                       OCR layer            AI layer
                                   (OcrProviderInterface)  (AIProviderInterface)
                                          └──────────┬──────────┘
                                                     ▼
                                            Structured extraction
                                                     ▼
                                              Validation engine
                                                     ▼
                                   ┌─────────────────┴─────────────────┐
                                   ▼                                   ▼
                              Human review                     SIHANDAL matching
                                                                     │
                                                       (after user confirmation)
                                                                     ▼
                                                              SIHANDAL API
                                                                     ▼
                                                                SIHANDAL V2
```

Key separations:

| Concern                                                         | Owned by         |
| --------------------------------------------------------------- | ---------------- |
| Document intelligence (upload, OCR, classification, extraction) | Agent Finance    |
| Verification engine (validation rules, anomaly indicators)      | Agent Finance    |
| Human review workspace, audit trail                             | Agent Finance    |
| AI chat / tool-calling analysis                                 | Agent Finance    |
| Financial master data (OPD, program, kegiatan, rekening)        | SIHANDAL via API |
| Financial transactions (SPP/SPM/SP2D, realisasi)                | SIHANDAL via API |

---

## 3. Application Architecture (this repository)

The repo is the official **Laravel 13 blank React starter kit** (Inertia v3,
React 19, Vite, Tailwind v4, Pest, PHPStan/Larastan, Pint, Wayfinder).
Agent Finance extends it rather than replacing it.

### 3.1 Backend (Laravel)

```
app/
├── Enums/                    # DocumentStatus, AnomalySeverity, ...
├── Events/                   # DocumentUploaded, ExtractionCompleted, ...
├── Exceptions/
├── Http/
│   ├── Controllers/          # Thin controllers, delegate to Actions/Services
│   ├── Middleware/           # EnsureUserIsAdmin, OPD scoping, HandleInertia
│   ├── Requests/             # Form Requests (validation)
│   └── Resources/            # API Resources (/api/v1 responses)
├── Jobs/                     # ProcessDocumentJob, ExtractTextJob, ...
├── Listeners/
├── Models/                   # Document, DocumentType, Opd, Role, ...
├── Policies/                 # DocumentPolicy, OpdPolicy, ...
├── Providers/
├── Services/
│   ├── Ai/                   # AIProviderInterface + OpenAI/Anthropic/Local impls
│   ├── Ocr/                  # OcrProviderInterface + Tesseract/Cloud impls
│   ├── Sihandal/             # SihandalApiInterface + SihandalClient
│   └── Validation/           # ValidationRule implementations
├── Actions/                  # Complex multi-model workflows (e.g. SyncToSihandal)
└── Support/
```

Conventions (from the starter kit and §63):

- PHP 8.3+ with constructor property promotion, readonly properties, enums.
- **Controllers are thin**: they authorize (Policy), validate (FormRequest),
  delegate to a service/action, and return an Inertia page or API Resource.
- No raw SQL scattered around; Eloquent + query builders.
- No hardcoded prompts or provider logic (§33, §34) — see §6 below.
- **Config-driven**: all knobs live in `config/agent-finance.php` and `.env`;
  secrets never in the database in plaintext.

### 3.2 Frontend (Inertia + React)

```
resources/js/
├── app.tsx                   # Inertia app bootstrap
├── components/
│   ├── ui/                   # Primitives (buttons, cards, badges)
│   ├── document-viewer/      # PDF/image pane, evidence highlights
│   └── layout/               # App sidebar, header, nav
├── features/                 # Feature components (extraction fields, pipelines)
├── hooks/
├── lib/                      # utils (cn), api helpers, formatters
├── pages/                    # Inertia pages (route targets)
│   ├── auth/                 # Login
│   ├── documents/            # index, show (review workspace), upload
│   ├── dashboard.tsx
│   ├── review.tsx
│   └── ...
├── routes/                   # Wayfinder-generated typed route functions
├── actions/                  # Wayfinder-generated typed action functions
└── types/                    # Shared TS types mirroring API resources
```

- Navigation is server-driven via Inertia; Wayfinder generates typed
  route/action functions so the frontend never hardcodes URLs.
- Data fetching uses Inertia props for pages; TanStack Query may be added
  later for polling-heavy panes (pipeline progress) without changing the
  architecture.

### 3.3 Why Inertia instead of a decoupled SPA

The starter kit ships Inertia v3 + session auth + Wayfinder. Reusing it gives:
session-based auth with CSRF out of the box, server-driven navigation,
typed routes, SSR-ready Vite setup, and one deployable artifact. The original
spec's "React Router + TanStack Query" SPA was explicitly reconsidered during
Phase 1 (decision recorded in `docs/IMPLEMENTATION_PLAN.md`).

---

## 4. Database Architecture

Engine: **MySQL 8** (decision recorded in `docs/IMPLEMENTATION_PLAN.md`).
Local dev runs MySQL via Docker Compose; CI and default `php artisan test`
runs use SQLite in-memory for speed (the starter kit's default) — all
migrations are written to be portable across both.

### 4.1 Entity map (Phase 1 → Phase 9)

Phase 1 (implemented now):

- `users` — + `opd_id` (nullable FK), `role_id` (nullable FK), `is_active`
- `roles` — id, name, slug, description (super_admin, admin, verifier, viewer)
- `opds` — id, code, name, is_active (local government work units)
- `document_types` — id, code, name, description, schema (JSON), version, is_active

Later phases (schema sketches, normalized per requirement at build time):

- **Documents**: `documents` (hash, storage paths, status, opd_id, fiscal_year,
  document_type_id), `document_pages` (page_number, ocr_text, confidence),
  `document_versions`, `document_processing_runs`
- **Extraction**: `document_extractions` (schema_name, schema_version, payload,
  prompt_id), `document_extraction_fields` (field, value, confidence, evidence
  JSON: page + bbox + source text), `document_entities`
- **Knowledge**: `document_chunks`, embeddings (provider-specific storage)
- **Quality**: `document_validations`, `document_anomalies` (type, severity,
  status, evidence, resolution), `document_reviews`
- **AI ops**: `ai_prompts` (name, version, system_prompt, schema, model,
  temperature, is_active), `ai_runs`, `ai_usage` (input/output tokens, cost
  estimate, duration, provider, model)
- **SIHANDAL**: `sihandal_syncs` (endpoint, payload, response, status, user),
  `sihandal_matches` (entity type, agent-finance value, sihandal resource,
  confidence, status, confirmed_by)
- **Platform**: `audit_logs` (user, action, subject, before/after, actor_type:
  human|ai|system), `notifications`

### 4.2 Indexing & integrity

- Foreign keys on all relations; `documents.file_hash` unique (duplicate
  detection at DB level, §7, §24).
- Composite indexes for the common filters: `(opd_id, status)`,
  `(document_type_id, fiscal_year)`, `(fiscal_year, created_at)`.
- JSON columns for versioned extraction payloads and evidence — never queried
  as the primary filter path (that's what indexed columns/entities are for).

### 4.3 Multi-tenancy (OPD isolation) — §36

- Every tenant-scoped table carries `opd_id`.
- Server-side scoping only: a global query scope applied via policies +
  controllers; users may only ever see documents of their OPD, except
  `super_admin`/cross-OPD verifiers.
- The `?opd_id=` parameter is **never trusted** from the client; filters are
  intersected with the user's allowed OPDs server-side.

---

## 5. Document Lifecycle & Queue Architecture

### 5.1 Lifecycle states — §5

```
uploaded → queued → processing → ocr → ai_analysis → extraction_complete
→ validation → review_required → approved → synced → completed

Failure states: processing_failed | ocr_failed | ai_failed
                | validation_failed | sync_failed
```

- Modeled as a PHP enum `App\Enums\DocumentStatus` persisted on
  `documents.status`; every transition is written to `audit_logs`
  (actor: system|ai|human).
- **Never silently fail**: each failure state records the failing stage, job
  ID and error on `document_processing_runs` and surfaces in the UI.

### 5.2 Queue topology — §30, §31

Jobs are small, single-purpose, and chained; nothing heavy runs in HTTP.

```
ProcessDocumentJob
  → ExtractTextJob          (embedded text or OCR per page)
  → ClassifyDocumentJob     (AI, schema-constrained)
  → ExtractFinancialDataJob (AI, typed schema, versioned prompt)
  → ValidateDocumentJob     (deterministic validation engine)
  → MatchSihandalDataJob    (queries SIHANDAL API, produces match candidates)
  → GenerateEmbeddingsJob   (RAG phase; only whitelisted content)
```

- Connection: `redis` (Docker), `database` fallback (no-Docker dev).
- Retries with exponential backoff; `tries`, `timeout`, and `backoff` per job.
- Idempotency via document processing version — re-running a stage never
  overwrites historical extraction versions (§38).
- Users can retry a failed stage from the UI **without re-uploading** (§31).

### 5.3 Progress observability — §29, §51

- `document_processing_runs` rows track stage-by-stage status; the UI renders
  the pipeline from this data. No fake progress.
- Structured logs (document ID, processing run ID, job ID, AI run ID, user,
  OPD, provider, model, duration, status, error).

---

## 6. AI Architecture

### 6.1 Provider abstraction — §34

```php
interface AIProviderInterface
{
    public function analyzeDocument(AiDocumentRequest $request): AiDocumentResult;
    public function extractStructuredData(AiExtractionRequest $request): AiExtractionResult;
    public function generateEmbedding(string $text): AiEmbeddingResult;
    public function chat(AiChatRequest $request): AiChatResult;
}
```

- Implementations: `OpenAIProvider`, `AnthropicProvider`, `GoogleProvider`,
  `LocalAIProvider` — selected by config, never by hardcoded logic.
- Provider-specific code never leaks beyond `app/Services/Ai`.
- All AI access goes through the interface; tests mock the interface only.

### 6.2 Structured extraction, confidence, evidence — §10–§13

- AI output must be **schema-valid JSON** (versioned schema per document type:
  `SPMSchema`, `InvoiceSchema`, `BkuSchema`, ...). Invalid output = `ai_failed`
  with retry, never silently accepted.
- Every extracted field stores: value, `confidence` (0–1), and **evidence**
  (page, optional bounding box, source OCR text) so the UI can jump to the
  document location. Low-confidence fields are highlighted, never hidden.
- Versioned prompts live in `ai_prompts` (name, version, system_prompt, schema,
  model, temperature, is_active). Every extraction records the prompt/model
  version used (§33). Active production prompts are never edited in place —
  a new version is created.

### 6.3 Cost control — §32, §56

- Document hash → skip re-processing identical files; results cached per
  (hash, prompt version, model).
- Token usage tracked per AI call (`ai_usage`: input/output tokens, model,
  provider, cost estimate, duration) and surfaced on a cost dashboard.
- Page-level processing; only required pages are sent to the provider.
  Prompt safety (§55): document text is untrusted input wrapped in strict
  system prompts; structured output only.

### 6.4 AI chat & tool calling — §19, §20, §47

```
User question → intent detection → tool selection
→ SIHANDAL API / Agent Finance DB (authorized, OPD-scoped)
→ structured result → LLM explanation (grounded in tool results)
```

- Tools: `get_sihandal_data` (every SIHANDAL list
  resource: opds, programs, kegiatan, sub_kegiatan,
  belanja, rekenings, sumber_dana, penerimaan,
  pengeluaran), `get_budget_summary`,
  `search_documents`, `validate_document`,
  `compare_document_with_sihandal`, ...
- Financial numbers always come from tool results, never from the LLM.
  Responses label statements as FACT / CALCULATION / AI INTERPRETATION / WARNING.
- Chat is read-only by design: it can propose, never execute writes.

---

## 7. OCR Architecture — §9

```php
interface OcrProviderInterface
{
    public function isRequired(DocumentPage $page): bool;
    public function process(DocumentPage $page): OcrResult;
}
```

- Digitally generated PDFs: extract embedded text first (no OCR).
- Scanned pages: OCR via `TesseractProvider` (Docker) or cloud providers.
- Mixed documents: page-by-page decisions.
- Stored per page: raw text, confidence, language, optional bounding boxes.

---

## 8. SIHANDAL Integration — §16–§18, §49, §50

### 8.1 Client abstraction

```php
interface SihandalApiInterface
{
    public function opds(...): SihandalCollection;
    public function programs(...): SihandalCollection;
    public function kegiatan(...): SihandalCollection;
    public function rekenings(...): SihandalCollection;
    public function belanja(...): SihandalCollection;
    public function tahunAnggaran(...): SihandalCollection;
    // ...one typed method per endpoint, no scattered HTTP calls
}
```

- `SihandalClient` (HTTP) is the only implementation; a `FakeSihandalClient`
  serves tests and offline development.
- Read endpoints are cached (Redis, short TTL) — SIHANDAL master data is
  reference data.

### 8.2 Matching & sync — §17, §48, §49

- `MatchSihandalDataJob` produces **candidates** (OPD/program/kegiatan/
  rekening) with confidence scores. Matching is a suggestion until a human
  confirms it in the UI.
- On confirmation, an `Action` performs the sync through the API, stores the
  full `sihandal_syncs` record (request, response, endpoint, user, timestamps),
  and the document advances to `synced`. Nothing is ever auto-deleted.
- **No direct AI write**: the AI layer can only _propose_; the write path
  requires explicit user confirmation and authorization (§18).

### 8.3 Failure resilience — §50

If SIHANDAL is unavailable, upload/OCR/extraction/review keep working; syncs
are recorded as `pending` and retried later. The document is never lost.

---

## 9. Validation Engine — §15, §25

Deterministic, extensible rule engine **separate from AI**:

- Input: a completed extraction (+ optional SIHANDAL reference data).
- Rules: `TotalMismatchRule` (line items vs. totals), `UnknownRekeningRule`
  (against SIHANDAL), `FiscalYearMismatchRule`, `MissingDocumentNumberRule`,
  duplicate detection (hash, number+vendor+amount+date, semantic similarity), ...
- Output: `document_validations` + `document_anomalies` records with
  `type`, `severity` (info|warning|high|critical), `status`, `evidence`,
  and resolution metadata (`resolved_at`, `resolved_by`).
- Anomalies are **factual indicators**, not AI verdicts — the system never
  claims fraud on AI say-so (§25).

---

## 10. Security Model — §35

| Layer          | Mechanism                                                                                                                                      |
| -------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| Authentication | Laravel session auth (starter kit), CSRF protection, rate-limited login                                                                        |
| Authorization  | Laravel Policies per model; roles (super_admin, admin, verifier, viewer); OPD scoping server-side                                              |
| File storage   | Private disk (`storage/app/private` or S3-compatible); **never** `public/uploads`; downloads via signed temporary URLs                         |
| Upload safety  | MIME + extension + size validation, file signature sniffing, corrupted-file rejection, SHA-256 hashing; virus-scanning architecture hook (§35) |
| API            | `/api/v1` behind Sanctum for machine clients; consistent JSON error envelopes                                                                  |
| Secrets        | `.env` only, never committed, never plaintext in DB (encrypted settings for admin-editable values)                                             |
| Headers/CORS   | Secure headers middleware; CORS restricted to configured origins                                                                               |
| Audit          | Every security-relevant action logged with actor type (human/ai/system)                                                                        |

### Authorization model — §36

- Roles are simple and few; permissions derive from role + OPD context:
    - `super_admin`: full platform, all OPDs, admin panel
    - `admin`: manage own OPD's documents and users
    - `verifier`: review/approve/reject, run validation & matching
    - `viewer`: read-only within own OPD
- Every document query goes through `DocumentPolicy` + OPD scope. Cross-OPD
  access by URL tampering is impossible by construction (scope applied in the
  model layer, not the controller parameter).

---

## 11. Observability & Performance — §51, §52

- Structured, contextual logging (document/processing/job/AI-run/user/OPD IDs).
- Admin processing monitor (Phase 9) over `document_processing_runs` and
  `ai_usage`.
- Performance: queue-first processing, Redis caching, pagination everywhere,
  chunked page processing, DB indexes per §4.2, lazy-loaded document previews.
- AI cost controls per §6.3 (hashing, caching, prompt versioning, page-level
  calls, usage tracking).

---

## 12. Testing Strategy — §53, §54

- **Backend**: Pest. Feature tests for auth, authorization/OPD isolation,
  upload validation, duplicate detection, lifecycle transitions, validation
  rules, matching, sync, audit logging.
- **AI/OCR/SIHANDAL**: mocked behind their interfaces; deterministic fixtures
  in `tests/Fixtures/documents/` (SPM, invoice, BKU samples). No paid AI calls
  in the default suite; `QUEUE_CONNECTION=sync` in tests for determinism.
- **Frontend**: Vitest + React Testing Library (added with Phase 2+).
- **E2E**: Playwright (Phase 10).

---

## 13. Deployment Topology (Docker-first) — §3

`docker-compose.yml` services:

| Service            | Image                           | Purpose                                                  |
| ------------------ | ------------------------------- | -------------------------------------------------------- |
| `app`              | PHP 8.3-FPM (custom Dockerfile) | Laravel application                                      |
| `nginx`            | nginx:alpine                    | HTTP entrypoint → app (and serves built frontend assets) |
| `mysql`            | mysql:8                         | Primary database                                         |
| `redis`            | redis:7                         | Cache, locks, queues                                     |
| `queue`            | app image                       | `php artisan queue:work`                                 |
| `scheduler`        | app image                       | `php artisan schedule:work`                              |
| `frontend`         | node:22                         | Vite dev server (dev) / static build (prod)              |
| `minio` (optional) | minio/minio                     | S3-compatible object storage for documents               |

- All configuration via environment variables documented in `.env.example`.
- `docker compose up -d` → migrate → seed → usable app.
- Production variant: hardened images, no source mounts, built assets,
  separate secrets management.

---

## 14. Roadmap Alignment

Phase ordering and current status live in `docs/IMPLEMENTATION_PLAN.md`.
Architecture-level future hooks (voice, WhatsApp, mobile, forecasting) are
served by the same boundaries: new clients hit `/api/v1`; new intelligence
features are new providers/rules/tools — never new bypasses around review.
