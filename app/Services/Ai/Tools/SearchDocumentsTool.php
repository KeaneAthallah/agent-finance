<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Ai\HybridSearchService;

/**
 * Semantic + keyword search over the whitelisted document
 * knowledge base (spec §19, §21). Read-only; OPD-scoped
 * by the hybrid search itself.
 */
final class SearchDocumentsTool implements ToolInterface
{
    public function __construct(
        private readonly HybridSearchService $search,
    ) {}

    public function name(): string
    {
        return 'search_documents';
    }

    public function description(): string
    {
        return 'Search processed document content (semantic + keyword) with optional fiscal year and document type filters. Returns ranked snippets.';
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'The search question or keywords',
                ],
                'fiscal_year' => [
                    'type' => 'integer',
                    'description' => 'Optional fiscal year filter',
                ],
                'document_type' => [
                    'type' => 'string',
                    'description' => 'Optional document type code filter (spm, sp2d, dpa, ...)',
                ],
            ],
            'required' => ['query'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments, User $user): array
    {
        $query = is_string($arguments['query'] ?? null) ? trim($arguments['query']) : '';

        if ($query === '') {
            throw new \InvalidArgumentException('query is required.');
        }

        $fiscalYear = isset($arguments['fiscal_year']) && is_numeric($arguments['fiscal_year'])
            ? (int) $arguments['fiscal_year']
            : null;

        $documentType = is_string($arguments['document_type'] ?? null) && $arguments['document_type'] !== ''
            ? $arguments['document_type']
            : null;

        $hits = $this->search->search($query, $user, $fiscalYear, $documentType, 10);

        // Unique document-level citations for the answer UI (roadmap P1-1).
        $refs = [];

        foreach ($hits as $hit) {
            $refs[$hit['document_id']] = ['type' => 'document', 'id' => $hit['document_id']];
        }

        return [
            'query' => $query,
            'hit_count' => $hits->count(),
            'hits' => $hits->all(),
            'refs' => array_values($refs),
        ];
    }
}
