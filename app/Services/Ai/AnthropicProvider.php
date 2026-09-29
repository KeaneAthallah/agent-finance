<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Anthropic provider placeholder. Phase 4 decides the default vendor and
 * implements the Messages API integration; until then configuration selecting
 * anthropic fails fast rather than silently degrading.
 */
final class AnthropicProvider implements AIProviderInterface
{
    public function analyzeDocument(AiDocumentRequest $request): AiDocumentResult
    {
        throw new RuntimeException('AnthropicProvider is not implemented yet (planned Phase 4). Configure the openai/local provider or the fake fallback.');
    }

    public function extractStructuredData(AiExtractionRequest $request): AiExtractionResult
    {
        throw new RuntimeException('AnthropicProvider is not implemented yet (planned Phase 4). Configure the openai/local provider or the fake fallback.');
    }

    public function generateEmbedding(string $text, string $model): AiEmbeddingResult
    {
        throw new RuntimeException('AnthropicProvider is not implemented yet (planned Phase 4). Configure the openai/local provider or the fake fallback.');
    }

    public function chat(AiChatRequest $request): AiChatResult
    {
        throw new RuntimeException('AnthropicProvider is not implemented yet (planned Phase 4). Configure the openai/local provider or the fake fallback.');
    }
}
