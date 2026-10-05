<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use App\Services\Sihandal\SihandalApiInterface;
use App\Services\Sihandal\TahunAnggaranService;
use InvalidArgumentException;
use RuntimeException;

/**
 * Generic read access to the SIHANDAL system of record:
 * every list resource the API exposes (spec §16, §19).
 * Read-only; the SIHANDAL API key governs OPD scoping,
 * the same seam every other SIHANDAL query uses.
 */
final class GetSihandalDataTool implements ToolInterface
{
    /** Every list resource the SIHANDAL API exposes. */
    private const RESOURCES = [
        'opds',
        'programs',
        'kegiatan',
        'sub_kegiatan',
        'belanja',
        'rekenings',
        'sumber_dana',
        'penerimaan',
        'pengeluaran',
        'tahun_anggaran',
    ];

    /** Resources whose rows belong to a fiscal year. */
    private const YEAR_SCOPED = ['kegiatan', 'sub_kegiatan', 'belanja', 'penerimaan', 'pengeluaran'];

    /** Resources with per-OPD rows. */
    private const OPD_SCOPED = ['kegiatan', 'belanja', 'penerimaan', 'pengeluaran'];

    public function __construct(
        private readonly SihandalApiInterface $sihandal,
        private readonly TahunAnggaranService $tahunAnggaran,
    ) {}

    public function name(): string
    {
        return 'get_sihandal_data';
    }

    public function description(): string
    {
        return 'List data from the SIHANDAL system of record. Resources: opds, programs, kegiatan, sub_kegiatan, belanja, rekenings, sumber_dana, penerimaan (revenue realizations), pengeluaran (expenditure), tahun_anggaran (the fiscal years SIHANDAL covers). Kegiatan, sub_kegiatan, belanja, penerimaan and pengeluaran are fiscal-year scoped; kegiatan, belanja, penerimaan and pengeluaran can also be filtered by opd_id; every resource accepts search. Search matches full names, not acronyms — an empty search does not mean the data is absent. Results are paginated, at most 100 rows per page.';
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'resource' => [
                    'type' => 'string',
                    'enum' => self::RESOURCES,
                    'description' => 'The SIHANDAL data resource to list',
                ],
                'fiscal_year' => [
                    'type' => 'integer',
                    'description' => 'Fiscal year (Tahun Anggaran), e.g. 2026. Only for year-scoped resources: kegiatan, sub_kegiatan, belanja, penerimaan, pengeluaran.',
                ],
                'opd_id' => [
                    'type' => 'integer',
                    'description' => 'Filter by OPD id. Only for kegiatan, belanja, penerimaan, pengeluaran.',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Keyword filter, matched by SIHANDAL against full names — acronyms often do not match',
                ],
                'page' => [
                    'type' => 'integer',
                    'description' => 'Result page, default 1',
                ],
                'per_page' => [
                    'type' => 'integer',
                    'description' => 'Rows per page, 1-100, default 20',
                ],
            ],
            'required' => ['resource'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(array $arguments, User $user): array
    {
        $resource = $arguments['resource'];

        if (! is_string($resource) || ! in_array($resource, self::RESOURCES, true)) {
            throw new InvalidArgumentException(
                'Unknown resource. Available: '.implode(', ', self::RESOURCES).'.',
            );
        }

        $filters = [];

        if (isset($arguments['fiscal_year'])) {
            if (! in_array($resource, self::YEAR_SCOPED, true)) {
                throw new InvalidArgumentException("fiscal_year does not apply to {$resource}.");
            }

            if (! is_numeric($arguments['fiscal_year'])) {
                throw new InvalidArgumentException('fiscal_year must be a year.');
            }

            $year = (int) $arguments['fiscal_year'];
            $tahunAnggaranId = $this->tahunAnggaran->idForYear($year, $this->sihandal);

            if ($tahunAnggaranId === null) {
                throw new RuntimeException("Fiscal year {$year} is not available in SIHANDAL.");
            }

            $filters['tahun_anggaran_id'] = $tahunAnggaranId;
        }

        if (isset($arguments['opd_id'])) {
            if (! in_array($resource, self::OPD_SCOPED, true)) {
                throw new InvalidArgumentException("opd_id does not apply to {$resource}.");
            }

            if (! is_numeric($arguments['opd_id'])) {
                throw new InvalidArgumentException('opd_id must be an integer.');
            }

            $filters['opd_id'] = (int) $arguments['opd_id'];
        }

        if (isset($arguments['search']) && is_string($arguments['search']) && $arguments['search'] !== '') {
            $filters['search'] = $arguments['search'];
        }

        $filters['page'] = max(1, (int) ($arguments['page'] ?? 1));
        $filters['per_page'] = max(1, min(100, (int) ($arguments['per_page'] ?? 20)));

        $collection = match ($resource) {
            'opds' => $this->sihandal->opds($filters),
            'programs' => $this->sihandal->programs($filters),
            'kegiatan' => $this->sihandal->kegiatan($filters),
            'sub_kegiatan' => $this->sihandal->subKegiatan($filters),
            'belanja' => $this->sihandal->belanja($filters),
            'rekenings' => $this->sihandal->rekenings($filters),
            'sumber_dana' => $this->sihandal->sumberDana($filters),
            'penerimaan' => $this->sihandal->penerimaan($filters),
            'pengeluaran' => $this->sihandal->pengeluaran($filters),
            'tahun_anggaran' => $this->sihandal->tahunAnggaran($filters),
        };

        return [
            'resource' => $resource,
            'count' => count($collection->items),
            'total' => $collection->total,
            'page' => $collection->page,
            'items' => $collection->items,
        ];
    }
}
