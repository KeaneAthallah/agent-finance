<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Google (Gemini) provider placeholder. Phase 4 decides the default vendor
 * and implements the integration; until then configuration selecting google
 * fails fast rather than silently degrading.
 */
final class GoogleProvider implements AIProviderInterface
{
    public function analyzeDocument(AiDocumentRequest $request): AiDocumentResult
    {
        throw new RuntimeException('GoogleProvider is not implemented yet (planned Phase 4). Configure the openai/local provider or the fake fallback.');
    }

    public function extractStructuredData(AiExtractionRequest $request): AiExtractionResult
    {
        throw new RuntimeException('GoogleProvider is not implemented yet (planned Phase 4). Configure the openai/local provider or the fake fallback.');
    }

    public function generateEmbedding(string $text, string $model): AiEmbeddingResult
    {
        throw new RuntimeException('GoogleProvider is not implemented yet (planned Phase 4). Configure the openai/local provider or the fake fallback.');
    }

    public function chat(AiChatRequest $request): AiChatResult
    {
        throw new RuntimeException('GoogleProvider is not implemented yet (planned Phase 4). Configure the openai/local provider or the fake fallback.');
    }
}
