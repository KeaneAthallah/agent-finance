<?php

namespace App\Services\Ai;

use App\Models\AiConversation;
use App\Models\AiRun;
use App\Models\AiUsage;
use App\Models\User;
use App\Services\Ai\Tools\ToolRegistry;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

/**
 * Grounded chat with tool calling (spec §19, §20, §47).
 * Flow: question → provider with tool definitions →
 * execute tool calls (read-only, OPD-scoped) → grounded
 * answer whose every statement carries a trust label.
 * Financial numbers only ever come from tool results.
 *
 * Since P0-1/P0-2/P0-3: conversations persist server-side;
 * per-user daily budgets are enforced up front; unparseable
 * JSON answers are repaired once; and post-parse verification
 * downgrades any "fact" that cannot be traced to an executed
 * tool (GroundedVerifier).
 */
final class ChatService
{
    /**
     * The model may chain tool rounds — an empty search
     * followed by a wider query, a fiscal-year lookup
     * before the data query — before it can answer.
     */
    private const MAX_TOOL_ROUNDS = 5;

    /** Most recent persisted turns replayed into context. */
    private const HISTORY_WINDOW = 20;

    public function __construct(
        private readonly AIProviderInterface $ai,
        private readonly ToolRegistry $tools,
        private readonly PromptRegistry $prompts,
        private readonly ChatBudgetService $budget,
        private readonly GroundedVerifier $grounding,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function chat(
        string $question,
        User $user,
        array $history = [],
        ?AiConversation $conversation = null,
    ): ChatAnswer {
        $this->budget->assertWithinBudget($user);

        $run = AiRun::query()->create([
            'prompt_id' => $this->prompts->active(PromptRegistry::CHAT_PROMPT)?->id,
            'stage' => 'chat',
            'provider' => (string) config('agent-finance.ai.provider'),
            'model' => (string) config('agent-finance.ai.default_model'),
            'prompt_name' => $this->prompts->active(PromptRegistry::CHAT_PROMPT)->name ?? 'builtin',
            'prompt_version' => $this->prompts->active(PromptRegistry::CHAT_PROMPT)->version ?? '1.0',
            'status' => 'running',
            'meta' => [
                'user_id' => $user->id,
                'conversation_id' => $conversation?->id,
            ],
        ]);

        $startedAt = now();

        try {
            $context = $conversation !== null
                ? $this->conversationHistory($conversation)
                : $history;

            $messages = array_merge(
                [['role' => 'system', 'content' => $this->systemPrompt()]],
                $context,
                [['role' => 'user', 'content' => $question]],
            );

            if ($conversation !== null) {
                $this->persistMessage($conversation, [
                    'role' => 'user',
                    'content' => $question,
                ]);
            }

            // Round 1: the provider decides whether it needs tools.
            $result = $this->ai->chat(new AiChatRequest(
                messages: $messages,
                tools: $this->tools->definitions(),
            ));

            $toolCalls = [];
            $executedTools = [];
            $jsonRetried = false;
            $sourceRefs = [];
            $inputTokens = (int) ($result->meta['input_tokens'] ?? 0);
            $outputTokens = (int) ($result->meta['output_tokens'] ?? 0);

            // Each round executes its calls and asks again, tools
            // included, until the model answers without calling
            // tools or the chain reaches its bound.
            for ($round = 1; $round <= self::MAX_TOOL_ROUNDS && $result->toolCalls !== []; $round++) {
                $executed = [];

                foreach ($result->toolCalls as $call) {
                    $name = (string) $call['name'];
                    $arguments = $call['arguments'];
                    $toolCalls[] = $call;
                    $executedTools[] = $name;

                    try {
                        $toolResult = $this->tools->execute($name, $arguments, $user);

                        $executed[] = [
                            'name' => $name,
                            'result' => $toolResult,
                        ];

                        $sourceRefs = array_merge($sourceRefs, $this->resultRefs($toolResult));
                    } catch (Throwable $exception) {
                        // A failed tool call becomes a WARNING in the
                        // grounded answer — never a silent gap (§20).
                        // Missing-model messages name the subject's ID,
                        // so they are replaced with a generic, OPD-safe
                        // message: the answer must never leak the
                        // existence of data outside the user's scope.
                        $executed[] = [
                            'name' => $name,
                            'error' => $exception instanceof ModelNotFoundException
                                ? 'The requested resource was not found or is not accessible.'
                                : $exception->getMessage(),
                        ];
                    }
                }

                $messages[] = [
                    'role' => 'assistant',
                    'content' => json_encode(['tool_calls' => $result->toolCalls], JSON_THROW_ON_ERROR),
                ];

                foreach ($executed as $toolResult) {
                    $messages[] = [
                        'role' => 'tool',
                        'content' => json_encode($toolResult, JSON_THROW_ON_ERROR),
                    ];
                }

                $result = $this->ai->chat(new AiChatRequest(
                    messages: $messages,
                    tools: $this->tools->definitions(),
                ));

                $inputTokens += (int) ($result->meta['input_tokens'] ?? 0);
                $outputTokens += (int) ($result->meta['output_tokens'] ?? 0);
            }

            $decoded = $this->decodeAnswer($result->content);

            // The model sometimes answers in prose despite the JSON contract.
            // Repair it once with an explicit instruction before falling back
            // to the "whole answer is an AI interpretation" path (§47).
            if ($decoded === null && config('agent-finance.ai.chat.json_retry', true)) {
                $jsonRetried = true;

                $repair = $this->ai->chat(new AiChatRequest(
                    messages: array_merge($messages, [
                        ['role' => 'user', 'content' => $this->repairInstruction()],
                    ]),
                    json: true,
                ));

                $inputTokens += (int) ($repair->meta['input_tokens'] ?? 0);
                $outputTokens += (int) ($repair->meta['output_tokens'] ?? 0);
                $result = $repair;
                $decoded = $this->decodeAnswer($result->content);
            }

            $answer = $this->buildAnswer($decoded, $result->content);

            $statements = $this->grounding->verify($answer['statements'], $executedTools);

            $sourceRefs = $this->dedupeRefs($sourceRefs);

            $run->update([
                'status' => 'completed',
                'input_tokens' => $inputTokens > 0 ? $inputTokens : null,
                'output_tokens' => $outputTokens > 0 ? $outputTokens : null,
                'duration_ms' => $startedAt->diffInMilliseconds(now()),
                'meta' => array_merge($result->meta, [
                    'tools_called' => $toolCalls,
                    'json_retry' => $jsonRetried,
                    'source_refs' => $sourceRefs,
                ]),
            ]);

            AiUsage::query()->create([
                'user_id' => $user->id,
                'ai_run_id' => $run->id,
                'provider' => $run->provider,
                'model' => $run->model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'duration_ms' => $run->duration_ms,
            ]);

            if ($conversation !== null) {
                $this->persistMessage($conversation, [
                    'role' => 'assistant',
                    'content' => $answer['summary'],
                    'statements' => array_map(
                        static fn (ChatStatement $statement): array => $statement->toArray(),
                        $statements,
                    ),
                    'tool_calls' => $toolCalls,
                    'ai_run_id' => $run->id,
                ]);
            }

            return new ChatAnswer(
                $answer['summary'],
                $statements,
                $toolCalls,
                array_merge($result->meta, ['source_refs' => $sourceRefs]),
            );
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'duration_ms' => $startedAt->diffInMilliseconds(now()),
            ]);

            throw $exception;
        }
    }

    /**
     * Streaming variant of chat() (roadmap P1-1): identical tool loop, budget,
     * persistence and grounding, but answer fragments are pushed through
     * $onEvent as they arrive and tool names after each execution. Providers
     * that cannot stream silently use their regular chat().
     *
     * Events:
     * - 'tool'  data: {"name": string} | {"name": string, "error": true}
     * - 'delta' data: {"delta": string} — parsed summary fragments, so the
     *            UI streams readable prose rather than raw provider JSON
     *
     * The caller emits the terminal 'done'/'error' event from the ChatAnswer.
     *
     * @param  callable(string $event, string $data): void  $onEvent
     */
    public function chatStream(
        string $question,
        User $user,
        ?AiConversation $conversation = null,
        ?callable $onEvent = null,
    ): ChatAnswer {
        $onEvent ??= static function (string $event, string $data): void {
            // No-op default: caller ignores events (plain synchronous use).
        };

        $this->budget->assertWithinBudget($user);

        $run = AiRun::query()->create([
            'prompt_id' => $this->prompts->active(PromptRegistry::CHAT_PROMPT)?->id,
            'stage' => 'chat',
            'provider' => (string) config('agent-finance.ai.provider'),
            'model' => (string) config('agent-finance.ai.default_model'),
            'prompt_name' => $this->prompts->active(PromptRegistry::CHAT_PROMPT)->name ?? 'builtin',
            'prompt_version' => $this->prompts->active(PromptRegistry::CHAT_PROMPT)->version ?? '1.0',
            'status' => 'running',
            'meta' => [
                'user_id' => $user->id,
                'conversation_id' => $conversation?->id,
                'stream' => true,
            ],
        ]);

        $startedAt = now();

        try {
            $context = $conversation !== null
                ? $this->conversationHistory($conversation)
                : [];

            $messages = array_merge(
                [['role' => 'system', 'content' => $this->systemPrompt()]],
                $context,
                [['role' => 'user', 'content' => $question]],
            );

            if ($conversation !== null) {
                $this->persistMessage($conversation, [
                    'role' => 'user',
                    'content' => $question,
                ]);
            }

            $result = null;
            $toolCalls = [];
            $executedTools = [];
            $jsonRetried = false;
            $sourceRefs = [];
            $inputTokens = 0;
            $outputTokens = 0;
            $streamed = '';

            $provider = $this->ai;

            for ($round = 1; $round <= self::MAX_TOOL_ROUNDS; $round++) {
                $request = new AiChatRequest(
                    messages: $messages,
                    tools: $this->tools->definitions(),
                );

                // Provider fragments are buffered locally and only reach the
                // wire as parsed summary text (see emitSummary), so the UI
                // streams readable prose instead of raw JSON.
                if ($provider instanceof StreamingAIProviderInterface) {
                    $result = $provider->chatStream($request, static function (string $delta) use (&$streamed): void {
                        $streamed .= $delta;
                    });
                } else {
                    $result = $provider->chat($request);
                }

                $inputTokens += (int) ($result->meta['input_tokens'] ?? 0);
                $outputTokens += (int) ($result->meta['output_tokens'] ?? 0);

                if ($result->toolCalls === []) {
                    break;
                }

                $executed = [];

                foreach ($result->toolCalls as $call) {
                    $name = (string) $call['name'];
                    $arguments = $call['arguments'];
                    $toolCalls[] = $call;
                    $executedTools[] = $name;

                    try {
                        $toolResult = $this->tools->execute($name, $arguments, $user);

                        $executed[] = [
                            'name' => $name,
                            'result' => $toolResult,
                        ];

                        $sourceRefs = array_merge($sourceRefs, $this->resultRefs($toolResult));

                        $onEvent('tool', json_encode(['name' => $name], JSON_THROW_ON_ERROR));
                    } catch (Throwable $exception) {
                        $executed[] = [
                            'name' => $name,
                            'error' => $exception instanceof ModelNotFoundException
                                ? 'The requested resource was not found or is not accessible.'
                                : $exception->getMessage(),
                        ];

                        $onEvent('tool', json_encode(['name' => $name, 'error' => true], JSON_THROW_ON_ERROR));
                    }
                }

                $messages[] = [
                    'role' => 'assistant',
                    'content' => json_encode(['tool_calls' => $result->toolCalls], JSON_THROW_ON_ERROR),
                ];

                foreach ($executed as $toolResult) {
                    $messages[] = [
                        'role' => 'tool',
                        'content' => json_encode($toolResult, JSON_THROW_ON_ERROR),
                    ];
                }
            }

            // The repair round is a single JSON-guaranteed call; its parsed
            // summary is streamed below like any other answer.
            $decoded = $this->decodeAnswer($result->content);

            if ($decoded === null && config('agent-finance.ai.chat.json_retry', true)) {
                $jsonRetried = true;

                $repair = $provider->chat(new AiChatRequest(
                    messages: array_merge($messages, [
                        ['role' => 'user', 'content' => $this->repairInstruction()],
                    ]),
                    json: true,
                ));

                $inputTokens += (int) ($repair->meta['input_tokens'] ?? 0);
                $outputTokens += (int) ($repair->meta['output_tokens'] ?? 0);

                $result = $repair;
                $decoded = $this->decodeAnswer($result->content);
            }

            $answer = $this->buildAnswer($decoded, $result->content);

            $statements = $this->grounding->verify($answer['statements'], $executedTools);

            $this->emitSummary($onEvent, $answer['summary'] !== '' || $streamed === ''
                ? $answer['summary']
                : $streamed);

            $statements = $this->grounding->verify($answer['statements'], $executedTools);

            $sourceRefs = $this->dedupeRefs($sourceRefs);

            $run->update([
                'status' => 'completed',
                'input_tokens' => $inputTokens > 0 ? $inputTokens : null,
                'output_tokens' => $outputTokens > 0 ? $outputTokens : null,
                'duration_ms' => $startedAt->diffInMilliseconds(now()),
                'meta' => array_merge($result->meta, [
                    'tools_called' => $toolCalls,
                    'json_retry' => $jsonRetried,
                    'source_refs' => $sourceRefs,
                ]),
            ]);

            AiUsage::query()->create([
                'user_id' => $user->id,
                'ai_run_id' => $run->id,
                'provider' => $run->provider,
                'model' => $run->model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'duration_ms' => $run->duration_ms,
            ]);

            if ($conversation !== null) {
                $this->persistMessage($conversation, [
                    'role' => 'assistant',
                    'content' => $answer['summary'],
                    'statements' => array_map(
                        static fn (ChatStatement $statement): array => $statement->toArray(),
                        $statements,
                    ),
                    'tool_calls' => $toolCalls,
                    'ai_run_id' => $run->id,
                ]);
            }

            return new ChatAnswer(
                $answer['summary'],
                $statements,
                $toolCalls,
                array_merge($result->meta, ['source_refs' => $sourceRefs]),
            );
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'duration_ms' => $startedAt->diffInMilliseconds(now()),
            ]);

            throw $exception;
        }
    }

    /**
     * Rebuild the assistant's context from persisted messages — never from
     * the client transcript. Most recent turns only, so the window stays
     * within the model's context (§47).
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function conversationHistory(AiConversation $conversation): array
    {
        return $conversation->messages()
            ->orderByDesc('id')
            ->take(self::HISTORY_WINDOW)
            ->get(['role', 'content'])
            ->reverse()
            ->values()
            ->map(fn (object $message): array => [
                'role' => (string) $message->role,
                'content' => (string) $message->content,
            ])
            ->all();
    }

    /**
     * @param  array{role: string, content: string, statements?: array<int, mixed>, tool_calls?: array<int, mixed>, ai_run_id?: int|null}  $attributes
     */
    private function persistMessage(AiConversation $conversation, array $attributes): void
    {
        $conversation->messages()->create($attributes);

        $conversation->touch();
    }

    /**
     * The chat system prompt: the labeling contract and the
     * grounding rules. Document text and tool output are
     * untrusted data, never instructions (spec §55).
     */
    private function systemPrompt(): string
    {
        return 'You are the Agent Finance assistant for Indonesian local government '
            .'financial data. You are READ-ONLY: you can look up and explain data, '
            .'never write, sync, or approve anything. '
            .'SIHANDAL is the system of record for regional government finance '
            .'(OPDs, programs, kegiatan, sub-kegiatan, belanja, penerimaan, '
            .'pengeluaran). Answer every question about SIHANDAL or its financials '
            .'from the get_sihandal_data tool; get_budget_summary only covers '
            .'documents uploaded to Agent Finance, never SIHANDAL. To read a '
            .'document\'s complete content (not just search snippets), use '
            .'get_document_text with its document ID. The word '
            .'"sihandal" in a question names the system — it is never a data '
            .'category, name, or search keyword. '
            .'If a search returns no rows, the term may be an acronym or '
            .'partial name — query the resource again without search '
            .'(per_page=100) and scan the rows yourself before concluding '
            .'the data does not exist. When a fiscal year is unavailable, '
            .'query the tahun_anggaran resource and state which fiscal '
            .'years SIHANDAL does cover. '
            .'Ground every answer in the tool results provided — financial numbers only '
            .'ever come from tool results, never from your own knowledge. '
            ."Label every statement exactly one of:\n"
            .'- FACT: a value that appears verbatim in a tool result (cite the tool name as source)'."\n"
            .'- CALCULATION: arithmetic over tool results (state the operands)'."\n"
            .'- AI INTERPRETATION: your own synthesis or reasoning'."\n"
            .'- WARNING: uncertainty, missing data, a mismatch, or inaccessible data'."\n"
            ."If the tool results do not contain the answer, reply with a single WARNING statement saying so.\n"
            .'Answer in the language of the question. Write a complete, conversational '
            .'summary of two to four sentences, and write every statement as a full '
            .'sentence with its context (fiscal year, units, OPD or source names). '
            .'Respond with a single JSON object and nothing else: '
            .'{"summary": string, "statements": [{"label": "FACT|CALCULATION|AI INTERPRETATION|WARNING", "text": string, "source": string|null}]}';
    }

    /**
     * Instruction sent on a one-shot repair round when the model answered in
     * prose: restate the JSON contract and forbid anything else.
     */
    private function repairInstruction(): string
    {
        return 'Your previous response was not a valid JSON object. Respond with ONLY a single JSON object and nothing else — no markdown code fences, no prose, no commentary: '
            .'{"summary": string, "statements": [{"label": "FACT|CALCULATION|AI INTERPRETATION|WARNING", "text": string, "source": string|null}]}';
    }

    /**
     * Extract the first markdown code block, or the
     * trimmed content when no block is present.
     */
    private function unwrapFencedJson(string $content): string
    {
        if (preg_match('/```[a-zA-Z0-9_-]*\s*(.*?)\s*```/s', $content, $matches) === 1) {
            return $matches[1];
        }

        return trim($content);
    }

    /**
     * Decode the labeled-statement JSON object, tolerating markdown fences.
     *
     * @return array<string, mixed>|null
     */
    private function decodeAnswer(string $content): ?array
    {
        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        // Models frequently wrap the JSON object in markdown code
        // fences despite the contract — unwrap it before giving up.
        $unwrapped = $this->unwrapFencedJson($content);

        if ($unwrapped === $content) {
            return null;
        }

        $decoded = json_decode($unwrapped, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>|null  $decoded
     * @return array{summary: string, statements: array<int, ChatStatement>}
     */
    private function buildAnswer(?array $decoded, string $content): array
    {
        // Providers that answer in prose still get the labeling contract —
        // the whole answer is the assistant's own interpretation.
        if ($decoded === null) {
            return [
                'summary' => mb_substr(trim($content), 0, 500),
                'statements' => [
                    new ChatStatement(ChatStatement::AI_INTERPRETATION, trim($content)),
                ],
            ];
        }

        $summary = is_string($decoded['summary'] ?? null) ? $decoded['summary'] : '';

        $raw = is_array($decoded['statements'] ?? null) ? $decoded['statements'] : [];

        $statements = [];

        foreach ($raw as $statement) {
            if (! is_array($statement)) {
                continue;
            }

            $label = is_string($statement['label'] ?? null)
                ? strtoupper($statement['label'])
                : ChatStatement::AI_INTERPRETATION;

            if (! in_array($label, ChatStatement::LABELS, true)) {
                $label = ChatStatement::AI_INTERPRETATION;
            }

            $text = is_string($statement['text'] ?? null) ? $statement['text'] : '';

            if ($text === '') {
                continue;
            }

            $statements[] = new ChatStatement(
                $label,
                $text,
                is_string($statement['source'] ?? null) && $statement['source'] !== ''
                    ? $statement['source']
                    : null,
            );
        }

        if ($statements === []) {
            $statements[] = new ChatStatement(
                ChatStatement::WARNING,
                'The assistant returned an empty answer.',
            );
        }

        return ['summary' => $summary, 'statements' => $statements];
    }

    /**
     * Citations (roadmap P1-1): tools may attach a `refs` list to their result
     * so the answer can deep-link back to the evidence. Only well-formed
     * document refs survive the cut.
     *
     * @param  array<string, mixed>  $result
     * @return array<int, array{type: string, id: int}>
     */
    private function resultRefs(array $result): array
    {
        $refs = $result['refs'] ?? [];

        if (! is_array($refs)) {
            return [];
        }

        $clean = [];

        foreach ($refs as $ref) {
            if (! is_array($ref)
                || ($ref['type'] ?? null) !== 'document'
                || ! is_numeric($ref['id'] ?? null)) {
                continue;
            }

            $clean[] = ['type' => 'document', 'id' => (int) $ref['id']];
        }

        return $clean;
    }

    /**
     * @param  array<int, array{type: string, id: int}>  $refs
     * @return array<int, array{type: string, id: int}>
     */
    private function dedupeRefs(array $refs): array
    {
        $unique = [];
        $seen = [];

        foreach ($refs as $ref) {
            $key = $ref['type'].':'.$ref['id'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $ref;
        }

        return $unique;
    }

    /**
     * Stream the parsed summary as small deltas so the client can render
     * prose progressively instead of a JSON blob.
     */
    private function emitSummary(callable $onEvent, string $summary): void
    {
        foreach ($this->summaryChunks($summary) as $chunk) {
            $onEvent('delta', json_encode(['delta' => $chunk], JSON_THROW_ON_ERROR));
        }
    }

    /**
     * @return array<int, string>
     */
    private function summaryChunks(string $summary, int $limit = 48): array
    {
        if ($summary === '') {
            return [];
        }

        $chunks = [];
        $remaining = $summary;

        while (mb_strlen($remaining) > $limit) {
            $pos = mb_strrpos(mb_substr($remaining, 0, $limit), ' ');
            $cut = $pos === false ? $limit - 1 : $pos + 1;

            $chunks[] = mb_substr($remaining, 0, $cut);
            $remaining = mb_substr($remaining, $cut);
        }

        $chunks[] = $remaining;

        return $chunks;
    }
}
