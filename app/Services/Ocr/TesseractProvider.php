<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Tesseract OCR running in the app container (tesseract-ocr + ind
 * traineddata are installed in the Docker image).
 */
final class TesseractProvider implements OcrProviderInterface
{
    public function isRequired(?string $embeddedText): bool
    {
        return blank($embeddedText);
    }

    public function process(string $absolutePath): OcrResult
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException("OCR input not found: {$absolutePath}");
        }

        $language = (string) config('agent-finance.ocr.language', 'ind+eng');

        $result = Process::timeout(120)
            ->run(['tesseract', $absolutePath, 'stdout', '-l', $language, '--dpi', '300']);

        if (! $result->successful()) {
            throw new RuntimeException('Tesseract OCR failed: '.$result->errorOutput());
        }

        return new OcrResult(
            text: trim($result->output()),
            confidence: 0.85, // mean-confidence refinement lands with the page pipeline (Phase 3)
            language: 'ind',
        );
    }
}
