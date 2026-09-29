<?php

namespace App\Services\Extraction;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Extracts embedded text from supported document formats (spec §9: digital
 * documents get embedded-text extraction first; only scanned pages fall
 * through to OCR in Phase 4+). Disk-agnostic: paths are relative to the
 * documents disk.
 */
final class TextExtractor
{
    public function __construct(
        private readonly \Smalot\PdfParser\Parser $pdfParser,
    ) {}

    /**
     * Extract per-page text. Returns a map of page number → text.
     *
     * @return array<int, string>
     */
    public function extract(string $disk, string $path, string $mimeType): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => $this->extractPdf($disk, $path),
            'csv' => $this->extractCsv($disk, $path),
            'docx' => $this->extractDocx($disk, $path),
            'xlsx' => $this->extractXlsx($disk, $path),
            'png', 'jpg', 'jpeg', 'webp', 'tif', 'tiff' => [1 => ''], // images: OCR stage handles
            default => throw new RuntimeException("Unsupported text extraction format: {$extension}"),
        };
    }

    /**
     * @return array<int, string>
     */
    private function extractPdf(string $disk, string $path): array
    {
        $stream = Storage::disk($disk)->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to read document: {$path}");
        }

        $tmp = tmpfile();

        if ($tmp === false) {
            fclose($stream);
            throw new RuntimeException('Unable to create a temporary file for PDF parsing.');
        }

        stream_copy_to_stream($stream, $tmp);
        fclose($stream);
        rewind($tmp);
        $meta = stream_get_meta_data($tmp);
        $uri = (string) ($meta['uri'] ?? '');

        try {
            $pdf = $this->pdfParser->parseFile($uri);

            $pages = [];

            foreach ($pdf->getPages() as $index => $page) {
                $pages[$index + 1] = trim($page->getText());
            }

            return $pages;
        } catch (\Throwable $exception) {
            throw new RuntimeException('PDF parsing failed: '.$exception->getMessage(), 0, $exception);
        } finally {
            fclose($tmp);
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractCsv(string $disk, string $path): array
    {
        $stream = Storage::disk($disk)->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to read document: {$path}");
        }

        $lines = [];
        $row = 0;

        while (($line = fgets($stream)) !== false) {
            $row++;
            $lines[$row] = rtrim($line, "\r\n");

            if ($row >= 500) {
                break; // cap extraction for huge CSVs; full parse is XLSX's job
            }
        }

        fclose($stream);

        return [1 => implode("\n", $lines)];
    }

    /**
     * DOCX: word/document.xml text nodes.
     *
     * @return array<int, string>
     */
    private function extractDocx(string $disk, string $path): array
    {
        $xml = $this->zipEntryToXml($disk, $path, 'word/document.xml');

        if ($xml === null) {
            return [1 => ''];
        }

        $text = '';

        $document = simplexml_load_string($xml);

        if ($document !== false) {
            $paragraphs = $document->xpath('//w:p');

            foreach ($paragraphs ?: [] as $paragraph) {
                $runs = $paragraph->xpath('.//w:t');

                $line = '';

                foreach ($runs ?: [] as $run) {
                    $line .= (string) $run;
                }

                $text .= $line."\n";
            }
        }

        return [1 => trim($text)];
    }

    /**
     * XLSX: concatenate all sheets as text (row-per-line).
     *
     * @return array<int, string>
     */
    private function extractXlsx(string $disk, string $path): array
    {
        $shared = $this->xlsxSharedStrings($disk, $path);

        $sheetXml = $this->zipEntryToXml($disk, $path, 'xl/worksheets/sheet1.xml');

        if ($sheetXml === null) {
            return [1 => ''];
        }

        $sheet = simplexml_load_string($sheetXml);

        $lines = [];

        if ($sheet !== false) {
            foreach ($sheet->xpath('//row') ?: [] as $row) {
                $cells = [];

                foreach ($row->xpath('./c') ?: [] as $cell) {
                    $attributes = $cell->attributes();
                    $type = (string) ($attributes['t'] ?? '');
                    $value = (string) ($cell->xpath('./v')[0] ?? '');

                    if ($type === 's') {
                        $index = (int) $value;
                        $value = $shared[$index] ?? '';
                    }

                    $cells[] = $value;
                }

                if ($cells !== []) {
                    $lines[] = implode(' | ', $cells);
                }

                if (count($lines) >= 1000) {
                    break;
                }
            }
        }

        return [1 => implode("\n", $lines)];
    }

    /**
     * @return array<int, string>
     */
    private function xlsxSharedStrings(string $disk, string $path): array
    {
        $xml = $this->zipEntryToXml($disk, $path, 'xl/sharedStrings.xml');

        if ($xml === null) {
            return [];
        }

        $strings = [];
        $root = simplexml_load_string($xml);

        if ($root !== false) {
            foreach ($root->xpath('//si') ?: [] as $index => $entry) {
                $parts = $entry->xpath('.//t');
                $text = '';

                foreach ($parts ?: [] as $part) {
                    $text .= (string) $part;
                }

                $strings[$index] = $text;
            }
        }

        return $strings;
    }

    private function zipEntryToXml(string $disk, string $path, string $entry): ?string
    {
        $stream = Storage::disk($disk)->readStream($path);

        if (! is_resource($stream)) {
            return null;
        }

        $tmp = tmpfile();

        if ($tmp === false) {
            fclose($stream);

            return null;
        }

        stream_copy_to_stream($stream, $tmp);
        fclose($stream);
        rewind($tmp);
        $meta = stream_get_meta_data($tmp);
        $uri = (string) ($meta['uri'] ?? '');

        try {
            $zip = new \ZipArchive();

            if ($zip->open($uri) !== true) {
                return null;
            }

            $contents = (string) $zip->getFromName($entry);
            $zip->close();

            return $contents !== '' ? $contents : null;
        } finally {
            fclose($tmp);
        }
    }
}
