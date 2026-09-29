<?php

namespace App\Services\Ai;

/**
 * Contract for the replaceable AI layer (spec §34). Provider-specific logic
 * must never leak beyond app/Services/Ai.
 */
interface AIProviderInterface
{
    public function analyzeDocument(AiDocumentRequest $request): AiDocumentResult;

    public function extractStructuredData(AiExtractionRequest $request): AiExtractionResult;

    public function generateEmbedding(string $text, string $model): AiEmbeddingResult;

    public function chat(AiChatRequest $request): AiChatResult;
}
