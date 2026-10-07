# AI Assistant — Roadmap to "Great"

Working TODO list for turning the AI Assistant from *useful* into *production-grade*.
Gaps are grouped by theme and prioritized (P0 = must-have foundation, P1 = high value,
P2 = polish). Each item lists the files it touches and a concrete acceptance test so a
task is either done or not done. Items already shipped in prior work are marked `[x]`.

Current state (Oct 2026): grounded chat with 5 read-only, OPD-scoped tools, labeled
statements (FACT / CALCULATION / AI INTERPRETATION / WARNING), hybrid (vector + lexical)
search, `ai_runs`/`ai_usage` recording, config-driven rate limit, versioned prompts in
`ai_prompts`, conversation persistence (list/open/rename/delete, server-side history),
per-user daily budget guardrails, JSON repair-once, a grounding-verification pass, SSE
streaming (`/ai-assistant/chat/stream`), Markdown/table rendering, and clickable
document citations. Big missing pieces: no admin usage UI, no `get_document` tool,
no prompt management UI.

Verify every item with the standard gates (`composer test`, `npm run types:check`) and a
Feature test in `tests/Feature/Ai/`. These are the mental models already in the repo:
`ChatService.php` (tool loop + label contract), `ToolRegistry.php` + `Tools/*`,
`HybridSearchService.php`, `FakeAIProvider.php` (deterministic, offline),
`prompts` in `ai_prompts` (immutable once active, new version = new row).

---

## Theme 1 — Conversation identity & persistence (P0)

- [x] **Persist conversations & messages** — `conversations` + `conversation_messages`
  tables (user_id, title; messages: role user/assistant, content, statements JSON,
  tool_calls JSON, ai_run_id). Persist on every turn; history rebuilt server-side.
  - Files: `migrations/*`, `app/Models/AiConversation.php`, `AiConversationMessage.php`,
    `AiAssistantController` (`index` loads list, `chat` creates/saves), `assistant.tsx`.
  - Accept: refresh mid-conversation restores messages; a new chat starts empty.
- [x] **Conversation list UI** — list, open, rename, delete, "new chat".
  History scoped to the current user.
  - Accept: switching conversations swaps transcript without losing messages.
- [x] **Send real history client-side** — the client sends `conversation_id` and the
  backend reconstructs history from `conversation_messages` (avoids client
  tampering/overflow). The old `history` field is ignored when a conversation is given.
  - Accept: `ChatTest` asserts history is rebuilt server-side, not echoed.

## Theme 2 — Streaming & response UX (P1)

- [x] **SSE streaming of the assistant reply** — POST `/ai-assistant/chat/stream` pushes
  `tool` progress, `delta` fragments (parsed summary text, not raw JSON), and a terminal
  `done`/`error` event as `text/event-stream`; budget is pre-checked so over-quota still
  gets a real 429. Keep `/chat` non-streaming as the fallback.
  - Files: `AiAssistantController::stream`, `ChatService::chatStream`,
    `StreamingAIProviderInterface` (`OpenAIProvider` = raw cURL + `CURLOPT_WRITEFUNCTION`;
    `FakeAIProvider` = deterministic slices; other providers fall back to `chat()`),
    `assistant.tsx` fetch + `lib/sse.ts` reader.
  - Accept: `tests/Feature/Ai/StreamTest.php` (well-formed events, done payload,
    non-streaming provider fallback, persistence) + `BudgetTest` stream 429.
- [x] **Markdown + tables in answers** — `components/markdown.tsx` (react-markdown +
  remark-gfm): headings, lists, links, GFM tables. Never `dangerouslySetInnerHTML`;
  react-markdown does not parse raw HTML (no rehype-raw), so model `<script>` stays
  escaped text.
  - Accept: `resources/js/components/markdown.test.tsx` renders a table and asserts
    no `<script>` element is created.
- [x] **Copy / regenerate / edit-last-question** per assistant message.
- [x] **Stop/cancel generation** button wired to `AbortController` aborting the SSE
  read; the partial answer that streamed in is kept as the assistant's message.
- [x] **Suggestion chips in empty state** — static list shown under the empty state;
  clicking fills the composer.

## Theme 3 — Grounding, verification & citations (P0/P1)

- [x] **JSON output enforcement** — providers with structured-output/JSON-mode → parse
  strictly; on parse failure **retry once** with a repair instruction before falling
  back to the loose `parseAnswer`. Retries are recorded in `ai_runs.meta.json_retry`.
  - Files: `ChatService::parseAnswer` (+ `AiChatRequest` gain a `response_format` flag),
    `OpenAIProvider`/`local` provider mapping.
  - Accept: ChatTest with a provider that emits fenced-invalid JSON recovers on retry.
- [x] **Grounding verification pass** — post-parse check: every `FACT`/`CALCULATION`
  statement's `source` must correspond to an executed tool result; a FACT with no
  matching result is re-labeled `AI INTERPRETATION` (or `WARNING`). Prevents
  hallucinated "facts".
  - Files: new `GroundedVerifier` service called from `ChatService`; unit test with a
    fabricated statement set.
- [x] **Labeling contract documented in prompt** — done in `ChatService::systemPrompt`
  + `FakeAIProvider`; keep in sync when the contract changes.
- [x] **Clickable citations** — tools return `refs: [{type: 'document', id}]`
  (`search_documents` deduped per document, `validate_document`,
  `compare_document_with_sihandal`); `ChatService` collects/dedupes them into
  `meta.source_refs` on both `/chat` and the SSE `done` event, and `assistant.tsx`
  renders "Sources:" chips linking to `/documents/{id}`.
  - Files: `Tools/*` outputs, `ChatService::resultRefs`/`dedupeRefs`, `assistant.tsx`
    `renderCitations`. Per-page anchors (`#page-{n}`) wait on the chunk
    `page`/`header` work in Theme 4.
- [ ] **Data-freshness line** — assistant states "Data SIHANDAL per {sync timestamp}".
  **Deferred (Oct 2026):** `SihandalSync` is a per-document write-attempt log, not a
  snapshot timestamp — needs a proper SIHANDAL sync-metadata concept first.

## Theme 4 — Tools & retrieval depth (P1)

- [ ] **`get_document` tool** — fetch a document's metadata, extraction fields,
  validation, and page/chunk text on demand: enables "ringkas dokumen 5",
  "berapa di dokumen SPM nomor …". OPD-scoped like `validate_document`.
  - Files: `app/Services/Ai/Tools/GetDocumentTool.php` + register in `ToolRegistry` +
    `FakeAIProvider` intent + ChatTest.
- [ ] **`list_documents` tool** — status/fiscal-year/type-filtered inventory with
  counts ("berapa dokumen belum divalidasi?").
- [ ] **SIHANDAL aggregation** — `get_sihandal_data` gains `aggregate` (sum/count by
  program/kegiatan/rekening, top-N, totals) instead of forcing the model to read pages
  of ≤100 rows. Keep raw rows for lookup.
  - Files: `GetSihandalDataTool` + SIHANDAL client (or client-side aggregation over
    paged fetch, cached 300s).
- [ ] **Fiscal-year / OPD comparison tool** — "total belanja OPD X vs Y" via a
  `compare_sihandal` tool (two `get_sihandal_data` calls summarized) or a dedicated
  endpoint.
- [ ] **Chunking: keep sentence alignment + make it token-aware and header-tagged** —
  track page numbers and section headers into `document_chunks` (`page`, `header`)
  so snippets show provenance and search can filter by page/header.
  - Files: `Chunker.php`, `ExtractTextJob`, `document_chunks` migration, `HybridSearchService` filters.
- [ ] **Re-rank candidates** (`CANDIDATE_POOL=1000`) with a cheap cross-encoder when
  available; otherwise document-grouped score display in results.
- [ ] **Query embedding resilience** — cache query embeddings (Redis, short TTL),
  batch chunk embeddings with backoff/retry, and surface embedding status
  (last generation, pending count, API 429 state) on the admin dashboard.
  This is the fix for the recurring Google 429 — degraded lexical search already
  exists as a fallback in `HybridSearchService`.
- [ ] **Number-aware search** — expand query terms "Rp 1 miliar", "104 triliun" into
  their digit forms, and vice-versa, before lexical scoring.

## Theme 5 — Cost, limits & admin governance (P0)

- [x] **Per-user AI budget/quota** — config `agent-finance.ai.chat.daily_message_limit`
  + `daily_token_budget`, enforced in `ChatService` via `ChatBudgetService` (friendly,
  generic 429 — never a 500); spend tracked through `ai_usage.user_id`.
  - Accept: BudgetTest asserts over-quota users get a graceful message (not a 500).
- [x] **Configurable rate limit** — hardcoded `throttle:10,1` replaced by the named
  limiter `throttle:ai-assistant` reading `agent-finance.ai.chat.rate_limit_per_minute`
  (per-user key; Laravel surfaces `Retry-After`).
- [ ] **Admin "AI usage" page** — table of conversations/users, per-model token & cost,
  tool latency, failure rate (data already in `ai_runs`/`ai_usage`). Read from
  `ReportsController`-style aggregates.
- [ ] **Embedding backfill command** — `php artisan ai:embeddings:backfill
  --document= --force` with resumable batches and a progress bar (complements the
  failed/timeout handling already in `GenerateEmbeddingsJob`).
- [ ] **Model selector** — per-conversation model choice surfaced as a dropdown
  (cheap/fast vs strong), persisted on the conversation, validated against a
  whitelist in config. Dropdown hidden for roles without permission.

## Theme 6 — Prompt hygiene & operations (P1)

- [ ] **Prompt management UI** — list `ai_prompts` (both `assistant_chat` and
  `financial_extraction`), view version diff, activate a version (immutable-once-active
  rule), rollback. PromptRegistry already supports versioned rows; add the admin page.
- [ ] **Version the *chat system prompt* too** — `ChatService::systemPrompt` is currently
  hardcoded; route it through `PromptRegistry::resolve(ChatService::CHAT_PROMPT)` so an
  operator can tune it without deploys (abandoned by `resolveExtraction` only today).
- [ ] **Per-tool instrumentation** — record per-tool latency, token deltas, and failure
  counts in `ai_runs.meta` (currently only tool names/args are stored).

## Theme 7 — Safety, security & audit (P1)

- [ ] **Prompt-injection regression suite** — document text / SIHANDAL rows / user input
  that try to override the label contract must be treated as data. Add adversarial
  fixtures to `tests/Feature/Ai/ChatTest.php` (existing rule: statement from tool result
  must be labeled; never trust content as instructions).
- [x] **Sanitize rendered HTML** — react-markdown never parses raw HTML (no rehype-raw
  plugin), so model output renders as escaped text; unit-tested in
  `resources/js/components/markdown.test.tsx` (no `<script>` element). Revisit if
  rehype-raw is ever added.
- [ ] **OPD-scope test for every tool** — a matrix test deriving scoping rules from
  `accessibleBy` for each registered tool (mirror `IsolationTest` for documents).
- [ ] **Audit trail** — full tool args/answers are already in `ai_runs`; add a
  viewer (admin) that can inspect and redact answers for audit requests.

## Theme 8 — Evaluation & testing (P1)

- [ ] **Golden-question harness** — `php artisan ai:evaluate [--set=…]` runs a
  YAML set of Q&A (with expected labels/keywords) against the live provider and prints
  a scorecard (precision@k, grounding %). Commit `tests/Feature/Ai/eval/*.yaml` as the
  source of truth for regressions.
- [ ] **Chat E2E** — `tests/e2e` flow: open assistant → ask a seeded question →
  assert labeled statements render and tool badges appear (Fake provider offline).
- [x] **Streaming contract test** — `tests/Feature/Ai/StreamTest.php`: SSE route returns
  well-formed `tool`/`delta`/`done` events for a Fake-provider answer, citations arrive
  as `meta.source_refs`, a non-streaming stub provider still completes the contract,
  and the turn persists; `BudgetTest` covers the stream 429.
- [x] **Conversation persistence tests** — create/append/rename/delete conversations;
  history rebuild equals persisted messages and ignores the client transcript;
  foreign ownership returns 404 (`tests/Feature/Ai/PersistenceTest.php`).

---

## Current state — already shipped (reference)

- `[x]` Hybrid search over `document_chunks` (lexical + vector; OPD-scoped; graceful degradation).
- `[x]` 5 read-only tools, multi-round tool loop (`MAX_TOOL_ROUNDS = 5`), labeled statements.
- `[x]` `ai_runs` / `ai_usage` per-call recording; prompts versioned in `ai_prompts`.
- `[x]` 10 req/min throttle + 2000-char validation; generic `ModelNotFoundException` handling.
- `[x]` Inference-time tools fail → WARNING statements (never silent gaps).
- `[x]` Conversation persistence + list/open/rename/delete UI; history rebuilt server-side (P0-1).
- `[x]` Per-user daily budget + config-driven rate limit (P0-2).
- `[x]` JSON repair-once (tracked in `ai_runs.meta.json_retry`) + grounding verification (P0-3).
- `[x]` SSE streaming (`/ai-assistant/chat/stream`), stop/cancel, copy/regenerate/edit, suggestion chips (P1-1).
- `[x]` Markdown + GFM table rendering (escaped, react-markdown) and document citations (`meta.source_refs`) (P1-1).

## Suggested sprint slicing (ordered)

1. **[x] P0-1 Persistence** (Theme 1) + **[x] P0-2 quota/rate guardrails** (Theme 5) — foundation for everything else.
2. **[x] P0-3 JSON enforcement + grounding verifier** (Theme 3) — quality floor.
3. **[x] P1-1 Streaming + markdown + citations** (Themes 2, 3) — the "wow" layer.
   (Data-freshness line deferred — see Theme 3 note.)
4. **P1-2 get_document/list_documents + aggregations** (Theme 4) — capability depth.
5. **P1-3 admin usage + prompt UI + eval harness** (Themes 5, 6, 8).