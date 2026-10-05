<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Sihandal\SihandalApiInterface;
use App\Services\Sihandal\TahunAnggaranService;

/**
 * Revenue (penerimaan) data from the SIHANDAL
 * system of record (spec §19). Read-only.
 */
final class GetRevenueTool implements ToolInterface
{
    public function __construct(
        private readonly SihandalApiInterface $sihandal,
        private readonly TahunAnggaranService $tahunAnggaran,
    ) {}

    public function name(): string
    {
        return 'get_revenue';
    }

    public function description(): string
    {
        return 'Get revenue (penerimaan) realizations from SIHANDAL for a fiscal year.';
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

        // The API filters by tahun_anggaran_id — resolve
        // the fiscal year first.
        if (isset($arguments['fiscal_year']) && is_numeric($arguments['fiscal_year'])) {
            $tahunAnggaranId = $this->tahunAnggaran->idForYear(
                (int) $arguments['fiscal_year'],
                $this->sihandal,
            );

            if ($tahunAnggaranId !== null) {
                $filters['tahun_anggaran_id'] = $tahunAnggaranId;
            }
        }

        $penerimaan = $this->sihandal->penerimaan($filters)->items;

        return [
            'count' => count($penerimaan),
            'penerimaan' => $penerimaan,
        ];
    }
}
