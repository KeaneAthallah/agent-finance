<?php

namespace App\Services\Ai\Tools;

use App\Models\Document;
use App\Models\DocumentPage;
use App\Models\User;
use InvalidArgumentException;
use LogicException;

/**
 * Full-text reading of one Agent Finance document (spec §19, §21).
 * search_documents returns ranked snippets; this returns the whole
 * page-by-page extracted text plus the latest structured extraction,
 * so the assistant can read a document's complete content. Read-only
 * and OPD-scoped by the same query scope and policy as every tool.
 */
final class GetDocumentTextTool implements ToolInterface
{
    /** Character budget for the combined page text (context safety). */
    private const MAX_CHARS = 20_000;

    public function name(): string
    {
        return 'get_document_text';
    }

    public function description(): string
    {
        return 'Return the full page-by-page extracted text and the latest structured extraction of one Agent Finance document (SPM, SP2D, SPP, DPA, RKA, ...) by its document ID. Use it when search snippets are not enough to answer a question about a specific document.';
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'document_id' => [
                    'type' => 'integer',
                    'description' => 'Agent Finance document ID',
                ],
            ],
            'required' => ['document_id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments, User $user): array
    {
        if (! is_numeric($arguments['document_id'] ?? null)) {
            throw new InvalidArgumentException('document_id is required.');
        }

        $document = Document::query()
            ->accessibleBy($user)
            ->findOrFail((int) $arguments['document_id']);

        if (! $user->can('view', $document)) {
            throw new LogicException('This document is not accessible to you.');
        }

        $pages = $document->pages()
            ->orderBy('page_number')
            ->get()
            ->map(fn (DocumentPage $page): array => [
                'page' => (int) $page->page_number,
                'text' => (string) $page->extracted_text,
                'text_source' => $page->text_source,
            ])
            ->values()
            ->all();

        $text = '';
        $truncated = false;

        foreach ($pages as $page) {
            $segment = "--- Page {$page['page']} ---\n".$page['text'];

            if (mb_strlen($text) + mb_strlen($segment) > self::MAX_CHARS) {
                $truncated = true;

                break;
            }

            $text .= ($text === '' ? '' : "\n\n").$segment;
        }

        $extraction = $document->extractions()
            ->where('status', 'completed')
            ->orderByDesc('version')
            ->first();

        return [
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'document_type' => $document->documentType?->code,
            'fiscal_year' => $document->fiscal_year,
            'status' => $document->status->value,
            'page_count' => count($pages),
            'text' => $text,
            'truncated' => $truncated,
            'extraction' => $extraction->payload ?? [],
            'refs' => [['type' => 'document', 'id' => $document->id]],
        ];
    }
}
