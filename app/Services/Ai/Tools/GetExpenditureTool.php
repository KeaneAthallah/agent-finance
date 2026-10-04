<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Sihandal\SihandalApiInterface;

/**
 * Expenditure (pengeluaran) data from the SIHANDAL
 * system of record (spec §19). Read-only.
 */
final class GetExpenditureTool implements ToolInterface
{
    public function __construct(
        private readonly SihandalApiInterface $sihandal,
    ) {}

    public function name(): string
    {
        return 'get_expenditure';
    }

    public function description(): string
    {
        return 'Get expenditure (pengeluaran) realizations from SIHANDAL for a fiscal year.';
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
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments, User $user): array
    {
        $filters = [];

        if (isset($arguments['fiscal_year']) && is_numeric($arguments['fiscal_year'])) {
            $filters['fiscal_year'] = (int) $arguments['fiscal_year'];
        }

        $pengeluaran = $this->sihandal->pengeluaran($filters)->items;

        return [
            'count' => count($pengeluaran),
            'pengeluaran' => $pengeluaran,
        ];
    }
}
