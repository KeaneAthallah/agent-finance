<?php

namespace App\Services\Extraction;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Renders single PDF pages to PNG images (poppler's pdftoppm, installed in
 * the Docker image) so scanned pages can be OCR'd (spec §9).
 */
final class PdfPageRenderer
{
    /**
     * Render one page and store it on the documents disk.
     *
     * @return string the stored path (relative to the disk)
     *
     * @throws RuntimeException when poppler is missing or rendering fails
     */
    public function renderPage(string $disk, string $pdfPath, int $pageNumber, int $dpi = 150): string
    {
        $localDisk = Storage::disk($disk);

        $pdfStream = $localDisk->readStream($pdfPath);

        if (! is_resource($pdfStream)) {
            throw new RuntimeException("Unable to read PDF: {$pdfPath}");
        }

        $tmpPdf = tmpfile();

        if ($tmpPdf === false) {
            fclose($pdfStream);
            throw new RuntimeException('Unable to create a temporary file.');
        }

        stream_copy_to_stream($pdfStream, $tmpPdf);
        fclose($pdfStream);

        $meta = stream_get_meta_data($tmpPdf);
        $pdfUri = (string) ($meta['uri'] ?? '');

        $outDir = rtrim(sys_get_temp_dir(), '/\\/').DIRECTORY_SEPARATOR.'af-pages';
        if (! is_dir($outDir)) {
            mkdir($outDir, 0775, true);
        }

        $prefix = $outDir.DIRECTORY_SEPARATOR.Str::uuid()->toString();

        try {
            $result = Process::timeout(120)->run([
                'pdftoppm', '-f', (string) $pageNumber, '-l', (string) $pageNumber,
                '-png', '-r', (string) $dpi, $pdfUri, $prefix,
            ]);

            if (! $result->successful()) {
                throw new RuntimeException('pdftoppm failed: '.$result->errorOutput());
            }

            // pdftoppm appends -<page>.png to the prefix.
            $expected = $prefix.($pageNumber >= 10 ? '' : '-0').$pageNumber.'.png';
            $candidates = glob($prefix.'*.png');

            if ($candidates === false || $candidates === []) {
                throw new RuntimeException('pdftoppm produced no output.');
            }

            $png = in_array($expected, $candidates, true) ? $expected : $candidates[0];

            $storedPath = sprintf(
                'pages/%s-%d.png',
                pathinfo($pdfPath, PATHINFO_FILENAME),
                $pageNumber,
            );

            $stored = $localDisk->put($storedPath, (string) file_get_contents($png));

            if (! $stored) {
                throw new RuntimeException('Failed to store the rendered page image.');
            }

            return $storedPath;
        } finally {
            foreach ((array) glob($prefix.'*.png') as $file) {
                @unlink((string) $file);
            }

            fclose($tmpPdf);
        }
    }
}
