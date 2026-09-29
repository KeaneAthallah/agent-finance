<?php

namespace App\Services\Ai;

/**
 * Chat request for the AI assistant (spec §19, §20).
 */
final class AiChatRequest
{
    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<int, array{name: string, description: string, parameters: array<string, mixed>}> $tools
     */
    public function __construct(
        public readonly array $messages,
        public readonly array $tools = [],
    ) {}
}
