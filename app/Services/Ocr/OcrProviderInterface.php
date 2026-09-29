<?php

namespace App\Services\Ocr;

/**
 * OCR abstraction (spec §9). Implementations: Tesseract (container),
 * cloud vision providers, etc. Deliberately decoupled from Eloquent models —
 * jobs pass plain values across this seam.
 */
interface OcrProviderInterface
{
    /**
     * Whether a page needs OCR (no embedded text was extracted).
     */
    public function isRequired(?string $embeddedText): bool;

    /**
     * Process a stored page image and return recognized text + metadata.
     *
     * @param  string  $absolutePath  absolute path to the page image
     */
    public function process(string $absolutePath): OcrResult;
}
