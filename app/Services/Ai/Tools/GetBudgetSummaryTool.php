<?php

namespace App\Services\Ai\Tools;

use App\Models\Document;
use App\Models\User;

/**
 * Budget summary per fiscal year, aggregated from the
 * extraction results of the user's accessible documents
 * (spec §19). Read-only; OPD-scoped.
 */
final class GetBudgetSummaryTool implements ToolInterface
{
    public function name(): string
    {
        return 'get_budget_summary';
    }

    public function description(): string
    {
        return 'Get the total extracted budget/expenditure amounts for a fiscal year, grouped by document type, across the documents uploaded to Agent Finance (not SIHANDAL — use get_sihandal_data for SIHANDAL data).';
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'fiscal_year' => [
                    'type' => 'integer',
                    'description' => 'Fiscal year (Tahun Anggaran), e.g. 2026',
                ],
            ],
            'required' => ['fiscal_year'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments, User $user): array
    {
        $fiscalYear = $arguments['fiscal_year'] ?? null;

        if (! is_numeric($fiscalYear)) {
            throw new \InvalidArgumentException('fiscal_year is required and must be a year.');
        }

        $fiscalYear = (int) $fiscalYear;

        $documents = Document::query()
            ->accessibleBy($user)
            ->where('fiscal_year', $fiscalYear)
            ->get(['id', 'opd_id', 'fiscal_year', 'document_type_id', 'metadata']);

        $byType = [];
        $total = 0.0;
        $count = 0;

        foreach ($documents as $document) {
            $amount = $document->metadata['extracted_amount'] ?? $document->metadata['extracted_total_budget'] ?? null;

            if (! is_numeric($amount)) {
                continue;
            }

            $count++;
            $total += (float) $amount;

            $type = $document->documentType->code ?? 'unknown';

            $byType[$type] ??= ['document_type' => $type, 'count' => 0, 'total_amount' => 0.0];

            $byType[$type]['count']++;
            $byType[$type]['total_amount'] += (float) $amount;
        }

        return [
            'fiscal_year' => $fiscalYear,
            'document_count' => $count,
            'total_amount' => $total,
            'by_type' => array_values($byType),
        ];
    }
}
