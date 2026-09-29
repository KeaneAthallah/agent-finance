<?php

namespace App\Services\Sihandal;

use App\Support\SihandalCollection;

/**
 * Contract for the SIHANDAL V2 REST API (spec §16). Agent Finance never
 * touches the SIHANDAL database — this client is the single integration seam.
 */
interface SihandalApiInterface
{
    /** @param array<string, mixed> $filters */
    public function opds(array $filters = []): SihandalCollection;

    /** @param array<string, mixed> $filters */
    public function programs(array $filters = []): SihandalCollection;

    /** @param array<string, mixed> $filters */
    public function kegiatan(array $filters = []): SihandalCollection;

    /** @param array<string, mixed> $filters */
    public function subKegiatan(array $filters = []): SihandalCollection;

    /** @param array<string, mixed> $filters */
    public function belanja(array $filters = []): SihandalCollection;

    /** @param array<string, mixed> $filters */
    public function rekenings(array $filters = []): SihandalCollection;

    /** @param array<string, mixed> $filters */
    public function sumberDana(array $filters = []): SihandalCollection;

    /** @param array<string, mixed> $filters */
    public function penerimaan(array $filters = []): SihandalCollection;

    /** @param array<string, mixed> $filters */
    public function pengeluaran(array $filters = []): SihandalCollection;

    /** @param array<string, mixed> $filters */
    public function tahunAnggaran(array $filters = []): SihandalCollection;
}
