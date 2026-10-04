<?php

namespace App\Services\Ai;

/**
 * Splits whitelisted document content into overlapping-free,
 * sentence-aligned chunks (spec §21). Chunks are small enough
 * for embedding models while keeping whole sentences together
 * so retrieval snippets read coherently.
 */
final class Chunker
{
    /** Target chunk size in characters. */
    public const MAX_CHUNK_CHARS = 400;

    /**
     * @return array<int, array{index: int, content: string}>
     */
    public function chunk(string $text): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($text === '') {
            return [];
        }

        // Sentence boundaries: period, exclamation, question mark,
        // or a newline — followed by whitespace or end of string.
        $sentences = preg_split('/(?<=[.!?])\s+|\n+/u', $text, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        $chunks = [];
        $current = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);

            if ($sentence === '') {
                continue;
            }

            // A single sentence longer than the limit becomes its
            // own chunk (hard-split at the limit so no data is lost).
            if (mb_strlen($sentence) > self::MAX_CHUNK_CHARS) {
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }

                foreach (mb_str_split(mb_substr($sentence, 0, 20000), self::MAX_CHUNK_CHARS) as $piece) {
                    $chunks[] = $piece;
                }

                continue;
            }

            if ($current !== '' && mb_strlen($current.' '.$sentence) > self::MAX_CHUNK_CHARS) {
                $chunks[] = $current;
                $current = $sentence;

                continue;
            }

            $current = $current === '' ? $sentence : $current.' '.$sentence;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return array_map(
            static fn (int $index, string $content): array => ['index' => $index, 'content' => $content],
            range(0, count($chunks) - 1),
            $chunks,
        );
    }
}
