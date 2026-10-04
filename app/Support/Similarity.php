<?php

namespace App\Support;

/**
 * Vector helpers shared by the RAG layer and the
 * semantic duplicate rule (spec §21, §15). Cosine
 * similarity is computed in PHP so it behaves
 * identically on SQLite (tests) and MySQL (prod).
 */
final class Similarity
{
    /**
     * Cosine similarity of two vectors (0 = orthogonal,
     * 1 = identical direction). Mismatched lengths
     * treat missing dimensions as 0.
     *
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public static function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $value) {
            $other = $b[$i] ?? 0.0;
            $dot += $value * $other;
            $normA += $value * $value;
            $normB += $other * $other;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
