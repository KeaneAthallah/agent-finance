<?php

namespace App\Services\Ai;

use Illuminate\Support\Collection;

/**
 * Deterministic fake provider used in tests and when no real provider is
 * configured. It never performs network calls (spec §53, §54).
 */
final class FakeAIProvider implements AIProviderInterface, StreamingAIProviderInterface
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
        // Deterministic bag-of-words pseudo-embedding: word order
        // is ignored, so texts that share vocabulary score similar
        // vectors — a stand-in for a real semantic model (spec §53).
        $vector = array_fill(0, 8, 0.0);

        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($text), $matches);

        foreach ($matches[0] as $word) {
            $hash = crc32($word);
            $vector[$hash % 8] += ($hash >> 8) % 2 === 0 ? 1 : -1;
        }

        $norm = sqrt(array_sum(array_map(
            static fn (float $value): float => $value * $value,
            $vector,
        )));

        if ($norm > 0.0) {
            $vector = array_map(
                static fn (float $value): float => $value / $norm,
                $vector,
            );
        }

        return new AiEmbeddingResult($vector, $model);
    }

    public function chat(AiChatRequest $request): AiChatResult
    {
        return $this->answer($request);
    }

    public function chatStream(AiChatRequest $request, callable $onDelta): AiChatResult
    {
        $result = $this->answer($request);

        // Deterministic streaming: the final content is pushed in small
        // slices so SSE consumers see text appear progressively (§44).
        foreach (str_split($result->content, 8) as $slice) {
            $onDelta($slice);
        }

        return $result;
    }

    /**
     * The deterministic "model" behind chat() and chatStream().
     */
    private function answer(AiChatRequest $request): AiChatResult
    {
        $messages = collect($request->messages);
        $last = $messages->last()['content'] ?? '';

        // Tool results are in context: produce the grounded,
        // labeled answer from them (deterministic, §53).
        if ($messages->contains('role', 'tool')) {
            return $this->groundedAnswer($messages);
        }

        // No tools available: plain echo, as before.
        if ($request->tools === []) {
            return new AiChatResult(
                content: '[fake] Anda bertanya: '.$last,
                meta: ['provider' => 'fake'],
            );
        }

        // Intent detection: pick the tool whose intent
        // matches the question and call it with arguments
        // found in the question text.
        $intent = $this->detectIntent((string) $last);

        if ($intent === null) {
            return new AiChatResult(
                content: '[fake] Anda bertanya: '.$last,
                meta: ['provider' => 'fake'],
            );
        }

        return new AiChatResult(
            content: '',
            toolCalls: [['name' => $intent['tool'], 'arguments' => $intent['arguments']]],
            meta: ['provider' => 'fake', 'input_tokens' => 12, 'output_tokens' => 8],
        );
    }

    /**
     * Deterministic intent → tool selection for tests and
     * offline development (Indonesian + English keywords).
     *
     * @return array{tool: string, arguments: array<string, mixed>}|null
     */
    private function detectIntent(string $question): ?array
    {
        $question = mb_strtolower($question);

        preg_match('/\b(20\d{2})\b/', $question, $yearMatch);
        $year = isset($yearMatch[1]) ? (int) $yearMatch[1] : 2026;

        preg_match('/\b(\d+)\b/', $question, $idMatch);
        $id = isset($idMatch[1]) ? (int) $idMatch[1] : 1;

        $intents = [
            ['pattern' => '/(anggaran|budget|ringkasan anggaran|total anggaran|jumlah anggaran)/', 'tool' => 'get_budget_summary', 'arguments' => ['fiscal_year' => $year]],
            ['pattern' => '/(banding|perbandingan|compare|versus|\bsihandal\b)/', 'tool' => 'compare_document_with_sihandal', 'arguments' => ['document_id' => $id]],
            ['pattern' => '/(isi (dokumen|lengkap)|teks (dokumen|lengkap)|full text|document text|baca dokumen|read document)/', 'tool' => 'get_document_text', 'arguments' => ['document_id' => $id]],
            ['pattern' => '/(cari|search|temukan|find|mencari)/', 'tool' => 'search_documents', 'arguments' => ['query' => $question]],
            ['pattern' => '/(validasi|anomali|validate|validation)/', 'tool' => 'validate_document', 'arguments' => ['document_id' => $id]],
            ['pattern' => '/(sub[\s-]?kegiatan)/', 'tool' => 'get_sihandal_data', 'arguments' => ['resource' => 'sub_kegiatan', 'fiscal_year' => $year]],
            ['pattern' => '/\bkegiatan\b/', 'tool' => 'get_sihandal_data', 'arguments' => ['resource' => 'kegiatan', 'fiscal_year' => $year]],
            ['pattern' => '/\bprogram/', 'tool' => 'get_sihandal_data', 'arguments' => ['resource' => 'programs']],
            ['pattern' => '/\bbelanja\b/', 'tool' => 'get_sihandal_data', 'arguments' => ['resource' => 'belanja', 'fiscal_year' => $year]],
            ['pattern' => '/(penerimaan|pendapatan|revenue)/', 'tool' => 'get_sihandal_data', 'arguments' => ['resource' => 'penerimaan', 'fiscal_year' => $year]],
            ['pattern' => '/(pengeluaran|expenditure)/', 'tool' => 'get_sihandal_data', 'arguments' => ['resource' => 'pengeluaran', 'fiscal_year' => $year]],
            ['pattern' => '/\brekening/', 'tool' => 'get_sihandal_data', 'arguments' => ['resource' => 'rekenings']],
            ['pattern' => '/(sumber[\s-]?dana)/', 'tool' => 'get_sihandal_data', 'arguments' => ['resource' => 'sumber_dana']],
            ['pattern' => '/(\bopd\b|unit kerja|satuan kerja)/', 'tool' => 'get_sihandal_data', 'arguments' => ['resource' => 'opds']],
        ];

        foreach ($intents as $intent) {
            if (preg_match($intent['pattern'], $question) === 1) {
                return ['tool' => $intent['tool'], 'arguments' => $intent['arguments']];
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, array{role: string, content: string}>  $messages
     */
    private function groundedAnswer(Collection $messages): AiChatResult
    {
        $statements = [];
        $warnings = 0;

        foreach ($messages->where('role', 'tool') as $message) {
            /** @var array{name?: mixed, result?: array<string, mixed>, error?: string} $payload */
            $payload = json_decode((string) $message['content'], true) ?? [];

            $name = is_string($payload['name'] ?? null) ? $payload['name'] : 'tool';

            if (isset($payload['error'])) {
                $warnings++;
                $statements[] = [
                    'label' => ChatStatement::WARNING,
                    'text' => ucfirst($name).' tidak tersedia: '.$payload['error'],
                    'source' => $name,
                ];

                continue;
            }

            $result = $payload['result'] ?? [];
            $statements[] = [
                'label' => ChatStatement::FACT,
                'text' => $this->factLine($name, $result),
                'source' => $name,
            ];
        }

        if ($statements === []) {
            $statements[] = [
                'label' => ChatStatement::WARNING,
                'text' => 'Tidak ada hasil tool yang dapat digunakan.',
                'source' => null,
            ];
        }

        $statements[] = [
            'label' => ChatStatement::AI_INTERPRETATION,
            'text' => 'Ringkasan disusun dari '.count($statements).' pernyataan FACT'.($warnings > 0 ? " dan {$warnings} peringatan" : '').'.',
            'source' => null,
        ];

        $content = json_encode([
            'summary' => 'Jawaban disusun dari hasil tool yang tersedia.',
            'statements' => $statements,
        ]);

        return new AiChatResult(
            content: (string) $content,
            meta: ['provider' => 'fake', 'input_tokens' => 40, 'output_tokens' => 30],
        );
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function factLine(string $tool, array $result): string
    {
        return match ($tool) {
            'get_budget_summary' => sprintf(
                'Total anggaran TA %d: Rp%s dari %d dokumen.',
                (int) ($result['fiscal_year'] ?? 0),
                number_format((float) ($result['total_amount'] ?? 0), 0, ',', '.'),
                (int) ($result['document_count'] ?? 0),
            ),
            'get_document_text' => sprintf(
                'Dokumen %s: teks %d halaman tersedia.',
                (string) ($result['document_number'] ?? '?'),
                (int) ($result['page_count'] ?? 0),
            ),
            'search_documents' => sprintf(
                'Ditemukan %d potongan dokumen yang cocok.',
                (int) ($result['hit_count'] ?? 0),
            ),
            'compare_document_with_sihandal' => sprintf(
                'Dokumen %s: %d kandidat SIHANDAL.',
                (string) ($result['document_number'] ?? '?'),
                count($result['candidates'] ?? []),
            ),
            'validate_document' => sprintf(
                'Status dokumen: %s.',
                (string) ($result['status'] ?? '?'),
            ),
            'get_sihandal_data' => sprintf(
                'Ditemukan %d dari %d entri %s.',
                (int) ($result['count'] ?? 0),
                (int) ($result['total'] ?? 0),
                (string) ($result['resource'] ?? 'data SIHANDAL')
            ),
            default => 'Data: '.mb_substr((string) json_encode($result), 0, 200),
        };
    }
}
