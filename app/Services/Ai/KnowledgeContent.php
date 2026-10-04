<?php

namespace App\Services\Ai;

use App\Models\Document;
use App\Models\DocumentPage;

/**
 * Builds the whitelisted knowledge-base content for a
 * document (spec §21, §45): its identity, its structured
 * extraction facts and the extracted page text — never the
 * raw upload itself. Shared by the embedding job and the
 * semantic duplicate rule so both embed the same format.
 */
final class KnowledgeContent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function content(Document $document, array $payload): string
    {
        $facts = array_filter($payload, static fn (mixed $value): bool => $value !== null);

        $header = sprintf(
            'Dokumen: %s. Jenis: %s. Tahun anggaran: %s. Nomor: %s. OPD: %s.',
            $document->original_filename,
            $document->documentType->code ?? 'tidak diketahui',
            (string) ($document->fiscal_year ?? 'tidak diketahui'),
            (string) ($document->document_number ?? 'tidak diketahui'),
            (string) ($document->opd->code ?? 'tidak diketahui'),
        );

        $structured = $facts !== []
            ? "\nFakta terekstraksi: ".json_encode($facts, JSON_UNESCAPED_UNICODE)
            : '';

        $pages = $document->pages()
            ->orderBy('page_number')
            ->get()
            ->filter(fn (DocumentPage $page): bool => filled($page->extracted_text))
            ->map(static fn (DocumentPage $page): string => 'Halaman '.((int) $page->page_number).': '.((string) $page->extracted_text))
            ->implode("\n");

        return trim($header.$structured."\n".$pages);
    }
}
