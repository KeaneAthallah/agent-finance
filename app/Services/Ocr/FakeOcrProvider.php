<?php

namespace App\Services\Ocr;

/**
 * Offline OCR stub. Used when the Tesseract binary is unavailable (e.g.
 * non-Docker dev on Windows) and in tests.
 */
final class FakeOcrProvider implements OcrProviderInterface
{
    public function isRequired(?string $embeddedText): bool
    {
        return blank($embeddedText);
    }

    public function process(string $absolutePath): OcrResult
    {
        return new OcrResult(
            text: '',
            confidence: 0.0,
            language: 'ind',
        );
    }
}
