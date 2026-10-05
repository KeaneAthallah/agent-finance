<?php

namespace App\Services\Sihandal;

/**
 * Fiscal-year resolution against the SIHANDAL contract (spec §16):
 * the API filters by the internal `tahun_anggaran_id`, not by the
 * year itself, so every year-scoped query first resolves the id
 * through the tahun-anggaran endpoint.
 */
final class TahunAnggaranService
{
    /**
     * Resolve a fiscal year (e.g. 2026) to the SIHANDAL
     * tahun_anggaran id, or null when SIHANDAL has no
     * such year (callers then skip the year filter).
     */
    public function idForYear(int $year, SihandalApiInterface $sihandal): ?int
    {
        foreach ($sihandal->tahunAnggaran()->items as $item) {
            if ((int) ($item['tahun'] ?? 0) === $year) {
                return (int) $item['id'];
            }
        }

        return null;
    }
}
