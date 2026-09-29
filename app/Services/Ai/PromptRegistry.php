<?php

namespace App\Services\Ai;

use App\Models\AiPrompt;

/**
 * Resolves the prompt for an AI call (spec §33). Prompts live in ai_prompts,
 * versioned; production prompts are immutable — editing creates a new
 * version. Falls back to built-in defaults when the table has no row yet, so
 * the pipeline works before an operator seeds prompts (and in tests).
 */
final class PromptRegistry
{
    public const EXTRACTION_PROMPT = 'financial_extraction';

    public function __construct() {}

    /**
     * Active prompt row for a name, or null when absent (caller uses the
     * built-in default and that decision is recorded on the ai_run).
     */
    public function active(string $name): ?AiPrompt
    {
        /** @var AiPrompt|null */
        return AiPrompt::query()
            ->where('name', $name)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Resolve everything an extraction call needs: system prompt, JSON schema
     * and prompt identity. DB prompt wins for the system message; the schema
     * itself comes from the typed registry (code-owned, versioned in git).
     *
     * @return array{system_prompt: string, prompt_name: string, prompt_version: string, prompt_id: int|null, source: string}
     */
    public function resolveExtraction(string $systemPrompt, ?int $promptId = null): array
    {
        $prompt = $promptId !== null ? AiPrompt::query()->find($promptId) : $this->active(self::EXTRACTION_PROMPT);

        if ($prompt === null) {
            return [
                'system_prompt' => $systemPrompt,
                'prompt_name' => 'builtin',
                'prompt_version' => '1.0',
                'prompt_id' => null,
                'source' => 'builtin',
            ];
        }

        return [
            'system_prompt' => $prompt->system_prompt."\n\n".$systemPrompt,
            'prompt_name' => $prompt->name,
            'prompt_version' => $prompt->version,
            'prompt_id' => $prompt->id,
            'source' => 'database',
        ];
    }
}
