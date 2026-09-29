<?php

namespace App\Services\Ai;

/**
 * Chat result grounded in tool results (spec §20, §47).
 */
final class AiChatResult
{
    /**
     * @param array<int, array{name: string, arguments: array<string, mixed>}> $toolCalls
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $content,
        public readonly array $toolCalls = [],
        public readonly array $meta = [],
    ) {}
}
