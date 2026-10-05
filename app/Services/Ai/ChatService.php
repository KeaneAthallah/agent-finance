<?php

namespace App\Services\Ai;

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
 */
final class ChatService
{
    /**
     * The model may chain tool rounds — an empty search
     * followed by a wider query, a fiscal-year lookup
     * before the data query — before it can answer.
     */
    private const MAX_TOOL_ROUNDS = 5;

    public function __construct(
        private readonly AIProviderInterface $ai,
        private readonly ToolRegistry $tools,
        private readonly PromptRegistry $prompts,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function chat(string $question, User $user, array $history = []): ChatAnswer
    {
        $run = AiRun::query()->create([
            'prompt_id' => $this->prompts->active(PromptRegistry::CHAT_PROMPT)?->id,
            'stage' => 'chat',
            'provider' => (string) config('agent-finance.ai.provider'),
            'model' => (string) config('agent-finance.ai.default_model'),
            'prompt_name' => $this->prompts->active(PromptRegistry::CHAT_PROMPT)->name ?? 'builtin',
            'prompt_version' => $this->prompts->active(PromptRegistry::CHAT_PROMPT)->version ?? '1.0',
            'status' => 'running',
            'meta' => ['user_id' => $user->id],
        ]);

        $startedAt = now();

        try {
            $messages = array_merge(
                [['role' => 'system', 'content' => $this->systemPrompt()]],
                $history,
                [['role' => 'user', 'content' => $question]],
            );

            // Round 1: the provider decides whether it needs tools.
            $result = $this->ai->chat(new AiChatRequest(
                messages: $messages,
                tools: $this->tools->definitions(),
            ));

            $toolCalls = [];
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

                    try {
                        $executed[] = [
                            'name' => $name,
                            'result' => $this->tools->execute($name, $arguments, $user),
                        ];
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

            $answer = $this->parseAnswer($result->content);

            $run->update([
                'status' => 'completed',
                'input_tokens' => $inputTokens > 0 ? $inputTokens : null,
                'output_tokens' => $outputTokens > 0 ? $outputTokens : null,
                'duration_ms' => $startedAt->diffInMilliseconds(now()),
                'meta' => array_merge($result->meta, ['tools_called' => $toolCalls]),
            ]);

            AiUsage::query()->create([
                'ai_run_id' => $run->id,
                'provider' => $run->provider,
                'model' => $run->model,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'duration_ms' => $run->duration_ms,
            ]);

            return new ChatAnswer(
                $answer['summary'],
                $answer['statements'],
                $toolCalls,
                $result->meta,
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
            .'documents uploaded to Agent Finance, never SIHANDAL. The word '
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
     * @return array{summary: string, statements: array<int, ChatStatement>}
     */
    private function parseAnswer(string $content): array
    {
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            // Models frequently wrap the JSON object in
            // markdown code fences despite the contract —
            // unwrap it so the labeled statements still parse.
            $unwrapped = $this->unwrapFencedJson($content);

            if ($unwrapped !== $content) {
                $decoded = json_decode($unwrapped, true);
            }
        }

        if (! is_array($decoded)) {
            // Providers that answer in prose still get the
            // labeling contract — the whole answer is the
            // assistant's own interpretation.
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
}
