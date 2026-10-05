<?php

namespace Tests\Unit\Services;

use App\Services\Ai\AiChatRequest;
use App\Services\Ai\OpenAIProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class OpenAIProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'agent-finance.ai.providers' => [
                'openai' => ['key' => null],
                'local' => ['key' => 'test-key', 'base_url' => 'https://example.test/v1'],
            ],
        ]);
    }

    public function test_chat_sends_tools_and_parses_tool_calls(): void
    {
        Http::fake([
            'example.test/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '',
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'get_revenue',
                                'arguments' => '{"fiscal_year":2026}',
                            ],
                            'extra_content' => ['google' => ['thought_signature' => 'sig-1']],
                        ]],
                    ],
                ]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ]),
        ]);

        $result = (new OpenAIProvider)->chat(new AiChatRequest(
            messages: [['role' => 'user', 'content' => 'Revenue 2026?']],
            tools: [[
                'name' => 'get_revenue',
                'description' => 'Revenue from SIHANDAL',
                'parameters' => ['type' => 'object', 'properties' => ['fiscal_year' => ['type' => 'integer']]],
            ]],
        ));

        $this->assertSame('get_revenue', $result->toolCalls[0]['name']);
        $this->assertSame(['fiscal_year' => 2026], $result->toolCalls[0]['arguments']);
        $this->assertSame('call_1', $result->toolCalls[0]['id'] ?? null);
        $this->assertSame('sig-1', $result->toolCalls[0]['thought_signature'] ?? null);

        Http::assertSent(function (Request $request): bool {
            /** @var array<string, mixed> $body */
            $body = json_decode($request->body(), true);

            /** @var array<int, array<string, mixed>> $tools */
            $tools = is_array($body['tools'] ?? null) ? $body['tools'] : [];

            /** @var array<string, mixed> $function */
            $function = is_array($tools[0]['function'] ?? null) ? $tools[0]['function'] : [];

            /** @var array<string, mixed> $parameters */
            $parameters = is_array($function['parameters'] ?? null) ? $function['parameters'] : [];

            return ($function['name'] ?? null) === 'get_revenue'
                && ($parameters['type'] ?? null) === 'object';
        });
    }

    public function test_chat_normalizes_tool_history_into_the_wire_protocol(): void
    {
        Http::fake([
            'example.test/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['content' => '{"summary": "Rp 500 juta"}'],
                ]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ]),
        ]);

        (new OpenAIProvider)->chat(new AiChatRequest(
            messages: [
                ['role' => 'user', 'content' => 'Revenue 2026?'],
                ['role' => 'assistant', 'content' => (string) json_encode([
                    'tool_calls' => [[
                        'id' => 'call_1',
                        'name' => 'get_revenue',
                        'arguments' => ['fiscal_year' => 2026],
                        'thought_signature' => 'sig-1',
                    ]],
                ])],
                ['role' => 'tool', 'content' => (string) json_encode(['name' => 'get_revenue', 'result' => []])],
            ],
        ));

        Http::assertSent(function (Request $request): bool {
            /** @var array<string, mixed> $body */
            $body = json_decode($request->body(), true);

            /** @var array<int, array<string, mixed>> $messages */
            $messages = is_array($body['messages'] ?? null) ? $body['messages'] : [];

            /** @var array<string, mixed> $assistant */
            $assistant = is_array($messages[1] ?? null) ? $messages[1] : [];

            /** @var array<string, mixed> $tool */
            $tool = is_array($messages[2] ?? null) ? $messages[2] : [];

            /** @var array<int, array<string, mixed>> $calls */
            $calls = is_array($assistant['tool_calls'] ?? null) ? $assistant['tool_calls'] : [];

            /** @var array<string, mixed> $function */
            $function = is_array($calls[0]['function'] ?? null) ? $calls[0]['function'] : [];

            /** @var array<string, array<string, string>> $extraContent */
            $extraContent = is_array($calls[0]['extra_content'] ?? null) ? $calls[0]['extra_content'] : [];

            return ($calls[0]['id'] ?? null) === 'call_1'
                && ($function['arguments'] ?? null) === '{"fiscal_year":2026}'
                && ($extraContent['google']['thought_signature'] ?? null) === 'sig-1'
                && ($assistant['content'] ?? null) === ''
                && ($tool['role'] ?? null) === 'tool'
                && ($tool['tool_call_id'] ?? null) === 'call_1';
        });
    }
}
