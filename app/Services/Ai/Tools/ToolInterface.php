<?php

namespace App\Services\Ai\Tools;

use App\Models\User;

/**
 * A read-only capability the AI assistant can call (spec §19,
 * §47). Tools are the only source of financial facts in a chat
 * answer — the LLM never invents values. Every tool enforces
 * OPD isolation server-side; tools can read, never write.
 */
interface ToolInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * JSON schema of the tool's parameters, sent to the
     * provider so it can construct valid calls.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed> JSON-serializable result
     *
     * @throws \InvalidArgumentException on missing/invalid arguments
     * @throws \LogicException when the subject is not accessible
     */
    public function execute(array $arguments, User $user): array;
}
