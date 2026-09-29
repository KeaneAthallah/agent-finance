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

### Phase 3 — Queue & OCR & Classification ⬜

- Redis queue in Docker; `ProcessDocumentJob` chain
- `document_pages`, `document_processing_runs` migrations
- Embedded-text extraction for digital PDFs; `OcrProviderInterface` +
  `TesseractProvider` (Docker image with tesseract-ocr + ind traineddata)
- `ClassifyDocumentJob` with mocked-provider tests; pipeline progress UI
  (real job states, TanStack Query polling introduced here)

### Phase 4 — AI Extraction ⬜

- `AIProviderInterface` + `OpenAIProvider` (+ env-selected mock for dev)
- `ai_prompts`, `ai_runs`, `ai_usage` migrations; versioned prompt registry
- `document_extractions`, `document_extraction_fields` (confidence +
  evidence: page, bbox, source text)
- `ExtractFinancialDataJob` with per-type schemas (`SPMSchema`, ...);
  review workspace panes: extraction fields with confidence badges + evidence
  jump-to-location in document viewer

### Phase 5 — Review, Validation, Audit ⬜

- `document_reviews`, `document_validations`, `document_anomalies`,
  `audit_logs` migrations
- Validation engine (rule classes): TOTAL_MISMATCH, FISCAL_YEAR_MISMATCH,
  MISSING_DOCUMENT_NUMBER, LOW_CONFIDENCE, duplicate scan
- Review queue page; approve/reject/request re-analysis flows; every manual
  correction audited (actor human vs AI distinguished)

### Phase 6 — SIHANDAL Integration ⬜

- `SihandalApiInterface` + `SihandalClient` + `FakeSihandalClient`
- `sihandal_matches`, `sihandal_syncs` migrations; matching job + candidates
  UI (confidence-ordered); confirm/reject match actions
- Sync action with full request/response history; pending-retry on failure;
  Sanctum-protected `/api/v1` for machine clients

### Phase 7 — AI Assistant, Tools, RAG ⬜

- Tool layer (`get_budget_summary`, `search_documents`,
  `compare_document_with_sihandal`, ...) — read-only
- Chat page with FACT/CALCULATION/AI INTERPRETATION/WARNING labels
- `document_chunks` + embeddings (whitelisted content only, metadata
  filters: OPD, fiscal year, type) + hybrid search (SQL + vector)

### Phase 8 — Anomalies & Financial Analysis ⬜

- Extensible anomaly engine (severity/status/resolution lifecycle)
- Duplicate detection (hash + number/vendor/amount/date + semantic)
- Document↔SIHANDAL comparison views with evidence on both sides

### Phase 9 — Reports, Admin, Cost ⬜

- Reports (documents processed, confidence distribution, review workload,
  sync health, AI token/cost dashboard)
- Admin area: users, roles, OPDs, document types, AI prompts (versioned,
  active-prompt immutability), settings, system health

### Phase 10 — Hardening ⬜

- Vitest + RTL frontend tests; Playwright E2E; security pass (rate limits,
  headers, malware-scan hook, secrets audit); production Docker targets;
  performance pass (indexes, pagination audit, queue tuning)

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
