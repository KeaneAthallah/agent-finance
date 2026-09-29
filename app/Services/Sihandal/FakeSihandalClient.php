<?php

namespace App\Services\Sihandal;

use App\Support\SihandalCollection;

/**
 * Offline SIHANDAL stand-in (spec §50). Returns deterministic sample master
 * data so development, demos and tests work without SIHANDAL V2 access.
 */
final class FakeSihandalClient implements SihandalApiInterface
{
    /** @param array<string, mixed> $filters */
    public function opds(array $filters = []): SihandalCollection
    {
        return new SihandalCollection(items: [
            ['id' => 1, 'code' => '4.01.0', 'name' => 'Dinas Pendidikan'],
            ['id' => 2, 'code' => '4.02.0', 'name' => 'Dinas Kesehatan'],
            ['id' => 3, 'code' => '5.02.0', 'name' => 'Dinas Pekerjaan Umum'],
        ]);
    }

    /** @param array<string, mixed> $filters */
    public function programs(array $filters = []): SihandalCollection
    {
        return new SihandalCollection(items: [
            ['id' => 1, 'opd_id' => 1, 'code' => '1.01', 'name' => 'Program Pengelolaan Pendidikan'],
            ['id' => 2, 'opd_id' => 2, 'code' => '2.01', 'name' => 'Program Penunjang Urusan Pemerintah Daerah Kabupaten/Kota'],
        ]);
    }

    /** @param array<string, mixed> $filters */
    public function kegiatan(array $filters = []): SihandalCollection
    {
        return new SihandalCollection(items: [
            ['id' => 1, 'program_id' => 1, 'code' => '1.01.1', 'name' => 'Pengelolaan Sekolah'],
            ['id' => 2, 'program_id' => 1, 'code' => '1.01.2', 'name' => 'Pembinaan Pendidikan'],
        ]);
    }

    /** @param array<string, mixed> $filters */
    public function subKegiatan(array $filters = []): SihandalCollection
    {
        return new SihandalCollection(items: [
            ['id' => 1, 'kegiatan_id' => 1, 'code' => '1.01.1.1', 'name' => 'Penyelenggaraan SD'],
        ]);
    }

    /** @param array<string, mixed> $filters */
    public function belanja(array $filters = []): SihandalCollection
    {
        return new SihandalCollection(items: [
            [
                'id' => 1001,
                'opd_id' => 1,
                'sub_kegiatan_id' => 1,
                'rekening' => '5.1.02.01.0001',
                'uraian' => 'Belanja Bahan Habis Pakai',
                'amount' => 125000000,
                'fiscal_year' => 2026,
            ],
        ]);
    }

    /** @param array<string, mixed> $filters */
    public function rekenings(array $filters = []): SihandalCollection
    {
        return new SihandalCollection(items: [
            ['id' => 1, 'code' => '5.1.02.01.0001', 'name' => 'Belanja Bahan Habis Pakai'],
            ['id' => 2, 'code' => '5.1.02.02.0001', 'name' => 'Belanja Jasa'],
            ['id' => 3, 'code' => '5.2.02.01.0001', 'name' => 'Belanja Modal Peralatan dan Mesin'],
        ]);
    }

    /** @param array<string, mixed> $filters */
    public function sumberDana(array $filters = []): SihandalCollection
    {
        return new SihandalCollection(items: [
            ['id' => 1, 'code' => 'DAU', 'name' => 'Dana Alokasi Umum'],
            ['id' => 2, 'code' => 'DAK', 'name' => 'Dana Alokasi Khusus'],
        ]);
    }

    /** @param array<string, mixed> $filters */
    public function penerimaan(array $filters = []): SihandalCollection
    {
        return new SihandalCollection(items: []);
    }

    /** @param array<string, mixed> $filters */
    public function pengeluaran(array $filters = []): SihandalCollection
    {
        return new SihandalCollection(items: []);
    }

    /** @param array<string, mixed> $filters */
    public function tahunAnggaran(array $filters = []): SihandalCollection
    {
        return new SihandalCollection(items: [
            ['id' => 1, 'year' => 2025, 'is_active' => false],
            ['id' => 2, 'year' => 2026, 'is_active' => true],
        ]);
    }
}
