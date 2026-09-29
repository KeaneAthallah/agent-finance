<?php

namespace App\Services\Ocr;

/**
 * OCR output for a single page (spec §9).
 */
final class OcrResult
{
    /**
     * @param array<int, array{x: int, y: int, w: int, h: int, text: string}>|null $boxes
     */
    public function __construct(
        public readonly string $text,
        public readonly float $confidence,
        public readonly string $language = 'ind',
        public readonly ?array $boxes = null,
    ) {}
}
