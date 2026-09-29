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
        $text = collect($request->pages)->pluck('text')->implode("\n");

        preg_match('/(SPM|SP2D|SPP|DPA|RKA|Invoice|Kwitansi)/i', $text, $typeMatch);

        $detected = isset($typeMatch[1]) ? strtolower($typeMatch[1]) : 'other';

        return new AiDocumentResult(
            documentType: $detected,
            confidence: $detected !== 'other' ? 0.85 : 0.50,
            meta: ['provider' => 'fake'],
        );
    }

    public function extractStructuredData(AiExtractionRequest $request): AiExtractionResult
    {
        $text = collect($request->pages)->pluck('text')->implode("\n");

        $number = null;
        if (preg_match('/Nomor\s*:?\s*([A-Za-z0-9\/\-.]+)/i', $text, $m)) {
            $number = $m[1];
        }

        $amount = null;
        if (preg_match('/Rp\s?([\d.,]+)/i', $text, $m)) {
            // Plain number: strip separators so downstream validation can compare.
            $amount = (float) str_replace([',', '.'], '', $m[1]);
        }

        $date = null;
        if (preg_match('/(\d{1,2})[ \/-](\d{1,2})[ \/-](\d{4})/', $text, $m)) {
            $date = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        $fiscalYear = null;
        if (preg_match('/TA( ?hun)?\s*:?\s*(\d{4})/i', $text, $m) || preg_match('/\b(20\d{2})\b/', $text, $m)) {
            $fiscalYear = (int) ($m[2] ?? $m[1]);
        }

        // Fill the schema-driven fields the fake provider can locate in text;
        // schema-required fields without a textual hit stay null. Per-field
        // confidence/evidence is deterministic (spec §53): fields found in
        // text score 0.9 with the page + matching line as evidence.
        $data = [];
        $fields = [];
        $fieldsInSchema = $request->schema['properties'] ?? [];

        $found = [
            'document_number' => $number,
            'amount' => $amount,
            'document_date' => $date,
            'fiscal_year' => $fiscalYear,
        ];

        foreach ($fieldsInSchema as $name => $property) {
            $value = $found[$name] ?? null;

            if ($value === null && ($property['type'] ?? 'string') === 'integer') {
                continue;
            }

            $data[$name] = $value;

            $fields[$name] = [
                'value' => $value,
                'confidence' => $value !== null ? 0.90 : 0.10,
                'source' => $value !== null ? ['page' => 1, 'source_text' => "field {$name} located in extracted text"] : null,
            ];
        }

        $scored = array_filter($fields, static fn (array $f) => $f['confidence'] >= 0.5);

        return new AiExtractionResult(
            data: $data,
            fields: $fields,
            confidence: count($scored) > 0 ? 0.90 : 0.40,
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
