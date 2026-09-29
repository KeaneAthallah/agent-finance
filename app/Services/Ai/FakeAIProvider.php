<?php

namespace App\Services\Ai;

/**
 * Deterministic fake provider used in tests and when no real provider is
 * configured. It never performs network calls (spec §53, §54).
 */
final class FakeAIProvider implements AIProviderInterface
{
    public function analyzeDocument(AiDocumentRequest $request): AiDocumentResult
    {
        return new AiDocumentResult(
            documentType: 'other',
            confidence: 0.50,
            meta: ['provider' => 'fake'],
        );
    }

    public function extractStructuredData(AiExtractionRequest $request): AiExtractionResult
    {
        $text = collect($request->pages)->pluck('text')->implode("\n");

        preg_match('/(SPM|SP2D|SPP|DPA|RKA|Invoice|Kwitansi)/i', $text, $typeMatch);

        $number = null;
        if (preg_match('/Nomor\s*:?\s*([A-Za-z0-9\/\-.]+)/i', $text, $m)) {
            $number = $m[1];
        }

        $amount = null;
        if (preg_match('/Rp\s?[\d.,]+/i', $text, $m)) {
            $amount = $m[0];
        }

        return new AiExtractionResult(
            data: array_filter([
                'document_type' => $typeMatch[1] ?? 'other',
                'document_number' => $number,
                'amount_text' => $amount,
            ]),
            fields: [],
            confidence: $number !== null ? 0.90 : 0.40,
            meta: ['provider' => 'fake'],
        );
    }

    public function generateEmbedding(string $text, string $model): AiEmbeddingResult
    {
        // Deterministic pseudo-embedding so tests can assert on it.
        $vector = [];
        $hash = md5($text);

        for ($i = 0; $i < 8; $i++) {
            $vector[] = hexdec(substr($hash, $i * 2, 2)) / 255.0;
        }

        return new AiEmbeddingResult($vector, $model);
    }

    public function chat(AiChatRequest $request): AiChatResult
    {
        $last = collect($request->messages)->last()['content'] ?? '';

        return new AiChatResult(
            content: '[fake] Anda bertanya: '.$last,
            meta: ['provider' => 'fake'],
        );
    }
}
