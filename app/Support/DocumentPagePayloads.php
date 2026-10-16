<?php

namespace App\Support;

use App\Models\Document;
use App\Models\DocumentPage;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the per-page payload handed to an AI provider (spec §9, §10).
 * Text always travels (the OCR/embedded text layer, and the fallback for
 * providers without vision). When vision is enabled the page image is
 * attached as a base64 data URI so a multimodal model can read the
 * document natively — layout, tables and handwriting included — rather
 * than only the OCR text. Pure transformation, mirroring App\Support\Similarity.
 */
final class DocumentPagePayloads
{
    /**
     * @return array<int, array{page: int, text: string, image?: string}>
     */
    public static function for(Document $document): array
    {
        $vision = (bool) config('agent-finance.ai.vision.enabled', false);
        $maxPages = (int) config('agent-finance.ai.vision.max_pages', 20);
        $maxBytes = (int) config('agent-finance.ai.vision.max_bytes', 4_000_000);

        return $document->pages()
            ->orderBy('page_number')
            ->get()
            ->map(function (DocumentPage $page) use ($vision, $maxPages, $maxBytes): array {
                $payload = [
                    'page' => (int) $page->page_number,
                    'text' => (string) $page->extracted_text,
                ];

                if (! $vision
                    || (int) $page->page_number > $maxPages
                    || ! filled($page->image_path)) {
                    return $payload;
                }

                $image = self::dataUri((string) $page->image_path, $maxBytes);

                if ($image !== null) {
                    $payload['image'] = $image;
                }

                return $payload;
            })
            ->values()
            ->all();
    }

    /**
     * Base64 data URI for a stored page image, or null when the file is
     * missing or larger than the per-image budget.
     */
    private static function dataUri(string $path, int $maxBytes): ?string
    {
        $storage = Storage::disk((string) config('agent-finance.documents.disk', 'local'));

        if (! $storage->exists($path)) {
            return null;
        }

        $size = $storage->size($path);

        if ($size <= 0 || $size > $maxBytes) {
            return null;
        }

        $mime = $storage->mimeType($path);

        return 'data:'.(is_string($mime) && $mime !== '' ? $mime : 'image/png')
            .';base64,'.base64_encode((string) $storage->get($path));
    }
}
