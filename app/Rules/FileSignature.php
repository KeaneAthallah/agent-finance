<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Validates that a file's binary signature (magic number) matches its
 * declared MIME type / extension (spec §7). Blocks renamed executables and
 * polyglot uploads.
 */
class FileSignature implements ValidationRule
{
    /**
     * Signature → extension(s) map. 'offset' = byte offset where the
     * signature starts in the file.
     *
     * @var array<string, array{bytes: string, offset: int}>
     */
    private const SIGNATURES = [
        'pdf' => ['bytes' => "%PDF-", 'offset' => 0],
        'png' => ['bytes' => "\x89PNG\r\n\x1a\n", 'offset' => 0],
        'jpg' => ['bytes' => "\xFF\xD8\xFF", 'offset' => 0],
        'jpeg' => ['bytes' => "\xFF\xD8\xFF", 'offset' => 0],
        'webp' => ['bytes' => "WEBP", 'offset' => 8],
        'tif' => ['bytes' => "II*\x00", 'offset' => 0],
        'tiff' => ['bytes' => "II*\x00", 'offset' => 0],
        'zip' => ['bytes' => "PK\x03\x04", 'offset' => 0], // docx/xlsx containers
    ];

    /**
     * Extension → which signature entry applies.
     *
     * @var array<string, string>
     */
    private const EXTENSION_TO_SIGNATURE = [
        'pdf' => 'pdf',
        'png' => 'png',
        'jpg' => 'jpg',
        'jpeg' => 'jpg',
        'webp' => 'webp',
        'tif' => 'tif',
        'tiff' => 'tif',
        'docx' => 'zip',
        'xlsx' => 'zip',
        'csv' => 'csv', // text — no magic number, validated as UTF-8-ish text
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('The :attribute is not a valid file.');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension() ?: '');
        $signatureKey = self::EXTENSION_TO_SIGNATURE[$extension] ?? null;

        if ($signatureKey === null) {
            $fail('The :attribute file type is not supported.');

            return;
        }

        // CSV: text format, reject binary content.
        if ($signatureKey === 'csv') {
            $head = (string) $value->getContent();

            if (! mb_check_encoding(substr($head, 0, 4096), 'UTF-8')) {
                $fail('The :attribute does not appear to be a valid CSV file.');
            }

            return;
        }

        $expected = self::SIGNATURES[$signatureKey];

        $handle = fopen($value->getRealPath(), 'rb');

        if ($handle === false) {
            $fail('The :attribute could not be read.');

            return;
        }

        fseek($handle, $expected['offset']);

        $bytes = (string) fread($handle, strlen($expected['bytes']));
        fclose($handle);

        if (! str_starts_with($bytes, $expected['bytes'])) {
            $fail('The :attribute file content does not match its format.');
        }
    }
}
