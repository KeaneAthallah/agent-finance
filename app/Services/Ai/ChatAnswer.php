<?php

namespace App\Services\Ai;

/**
 * The assistant's grounded answer (spec §20, §47):
 * a summary plus individually labeled statements, the
 * tool calls that produced them, and provider metadata.
 */
final class ChatAnswer
{
    /**
     * @param  array<int, ChatStatement>  $statements
     * @param  array<int, array{id?: string, name: string, arguments: array<string, mixed>, thought_signature?: string|null}>  $toolCalls
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $summary,
        public readonly array $statements,
        public readonly array $toolCalls = [],
        public readonly array $meta = [],
    ) {}

    /**
     * @return array{summary: string, statements: array<int, array{label: string, text: string, source: string|null}>, tool_calls: array<int, array{id?: string, name: string, arguments: array<string, mixed>, thought_signature?: string|null}>, meta: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'statements' => array_map(
                static fn (ChatStatement $statement): array => $statement->toArray(),
                $this->statements,
            ),
            'tool_calls' => $this->toolCalls,
            'meta' => $this->meta,
        ];
    }
}
