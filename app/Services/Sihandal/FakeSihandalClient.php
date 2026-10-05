<?php

namespace App\Services\Sihandal;

use App\Support\SihandalCollection;

/**
 * Offline SIHANDAL stand-in (spec §50). Returns deterministic sample
 * master data shaped like the real API (spec §16) so development,
 * demos and tests work without SIHANDAL V2 access.
 */
final class FakeSihandalClient implements SihandalApiInterface
{
    /** @var array<int, array<string, mixed>> */
    private const OPD_ITEMS = [
        ['id' => 1, 'kode' => '4.01.0', 'nama' => 'Dinas Pendidikan', 'total_pagu' => 48250000000],
        ['id' => 2, 'kode' => '4.02.0', 'nama' => 'Dinas Kesehatan', 'total_pagu' => 61400000000],
        ['id' => 3, 'kode' => '5.02.0', 'nama' => 'Dinas Pekerjaan Umum', 'total_pagu' => 88750000000],
    ];

    /** @var array<int, array<string, mixed>> */
    private const TAHUN_ANGGARAN_ITEMS = [
        [
            'id' => 1,
            'tahun' => '2025',
            'tanggal_mulai' => '2025-01-01',
            'tanggal_selesai' => '2025-12-31',
            'status' => 'closed',
            'is_active' => false,
            'is_open' => false,
        ],
        [
            'id' => 2,
            'tahun' => '2026',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-12-31',
            'status' => 'open',
            'is_active' => true,
            'is_open' => true,
        ],
    ];

    /**
     * The matching fixtures: two 2026 belanja lines of
     * Rp125.000.000 — one for OPD 4.01.0 carrying the
     * SPM-777/2026 document number, one for OPD 4.02.0
     * carrying SPM-999/2026.
     *
     * @return array<int, array<string, mixed>>
     */
    private function belanjaItems(): array
    {
        return [
            [
                'id' => 1001,
                'opd_id' => 1,
                'sub_kegiatan_id' => 1,
                'rekening_id' => 1,
                'sumber_dana_id' => 1,
                'tahun_anggaran_id' => 2,
                'pagu' => 125000000,
                'realisasi' => 0,
                'dana_di_commit' => 0,
                'available_pagu' => 125000000,
                'document_number' => 'SPM-777/2026',
                'rekening' => [
                    'id' => 1,
                    'kode' => '5.1.02.01.0001',
                    'nama' => 'Belanja Bahan Habis Pakai',
                    'tipe' => 'belanja',
                    'parent_id' => null,
                    'is_kas_leaf' => false,
                ],
                'sumber_dana' => [
                    'id' => 1,
                    'nama_sumber_dana' => 'Dana Alokasi Umum',
                ],
                'sub_kegiatan' => [
                    'id' => 1,
                    'kegiatan_id' => 1,
                    'kode_sub_kegiatan' => '1.01.1.1.0001',
                    'nama_sub_kegiatan' => 'Penyelenggaraan SD',
                    'pagu' => 20642456000,
                    'realisasi' => 0,
                    'persentase' => 0,
                    'kegiatan' => [
                        'id' => 1,
                        'program_id' => 1,
                        'opd_id' => 1,
                        'kode_kegiatan' => '1.01.1',
                        'nama_kegiatan' => 'Pengelolaan Sekolah',
                        'pagu' => 28105829700,
                        'realisasi' => 0,
                        'persentase' => 0,
                    ],
                ],
            ],
            [
                'id' => 1002,
                'opd_id' => 2,
                'sub_kegiatan_id' => 2,
                'rekening_id' => 2,
                'sumber_dana_id' => 1,
                'tahun_anggaran_id' => 2,
                'pagu' => 125000000,
                'realisasi' => 0,
                'dana_di_commit' => 0,
                'available_pagu' => 125000000,
                'document_number' => 'SPM-999/2026',
                'rekening' => [
                    'id' => 2,
                    'kode' => '5.1.02.01.0002',
                    'nama' => 'Belanja Bahan Habis Pakai',
                    'tipe' => 'belanja',
                    'parent_id' => null,
                    'is_kas_leaf' => false,
                ],
                'sumber_dana' => [
                    'id' => 1,
                    'nama_sumber_dana' => 'Dana Alokasi Umum',
                ],
                'sub_kegiatan' => [
                    'id' => 2,
                    'kegiatan_id' => 2,
                    'kode_sub_kegiatan' => '1.01.1.1.0002',
                    'nama_sub_kegiatan' => 'Pembinaan Pendidikan',
                    'pagu' => 20642456000,
                    'realisasi' => 0,
                    'persentase' => 0,
                    'kegiatan' => [
                        'id' => 2,
                        'program_id' => 1,
                        'opd_id' => 2,
                        'kode_kegiatan' => '1.01.2',
                        'nama_kegiatan' => 'Pembinaan Pendidikan',
                        'pagu' => 28105829700,
                        'realisasi' => 0,
                        'persentase' => 0,
                    ],
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $filters */
    public function opds(array $filters = []): SihandalCollection
    {
        return new SihandalCollection(items: self::OPD_ITEMS);
    }

    /** @param array<string, mixed> $filters */
    public function programs(array $filters = []): SihandalCollection
    {
        return new SihandalCollection(items: [
            ['id' => 1, 'opd_id' => 1, 'kode_program' => '1.01', 'nama_program' => 'Program Pengelolaan Pendidikan'],
            ['id' => 2, 'opd_id' => 2, 'kode_program' => '2.01', 'nama_program' => 'Program Penunjang Urusan Pemerintah Daerah Kabupaten/Kota'],
        ]);
    }

    /** @param array<string, mixed> $filters */
    public function kegiatan(array $filters = []): SihandalCollection
    {
        return new SihandalCollection(items: [
            ['id' => 1, 'program_id' => 1, 'opd_id' => 1, 'kode_kegiatan' => '1.01.1', 'nama_kegiatan' => 'Pengelolaan Sekolah', 'pagu' => 28105829700, 'realisasi' => 0, 'persentase' => 0],
            ['id' => 2, 'program_id' => 1, 'opd_id' => 2, 'kode_kegiatan' => '1.01.2', 'nama_kegiatan' => 'Pembinaan Pendidikan', 'pagu' => 28105829700, 'realisasi' => 0, 'persentase' => 0],
        ]);
    }

    /** @param array<string, mixed> $filters */
    public function subKegiatan(array $filters = []): SihandalCollection
    {
        return new SihandalCollection(items: [
            ['id' => 1, 'kegiatan_id' => 1, 'kode_sub_kegiatan' => '1.01.1.1.0001', 'nama_sub_kegiatan' => 'Penyelenggaraan SD', 'pagu' => 20642456000, 'realisasi' => 0, 'persentase' => 0],
            ['id' => 2, 'kegiatan_id' => 2, 'kode_sub_kegiatan' => '1.01.1.1.0002', 'nama_sub_kegiatan' => 'Pembinaan Pendidikan', 'pagu' => 20642456000, 'realisasi' => 0, 'persentase' => 0],
        ]);
    }

    /** @param array<string, mixed> $filters */
    public function belanja(array $filters = []): SihandalCollection
    {
        $items = $this->belanjaItems();

        if (isset($filters['tahun_anggaran_id'])) {
            $items = array_values(array_filter(
                $items,
                static fn (array $item): bool => (int) $item['tahun_anggaran_id'] === (int) $filters['tahun_anggaran_id'],
            ));
        }

        return new SihandalCollection(items: $items);
    }

    /** @param array<string, mixed> $filters */
    public function rekenings(array $filters = []): SihandalCollection
    {
        return new SihandalCollection(items: [
            ['id' => 1, 'kode' => '5.1.02.01.0001', 'nama' => 'Belanja Bahan Habis Pakai', 'tipe' => 'belanja', 'parent_id' => null, 'is_kas_leaf' => false],
            ['id' => 2, 'kode' => '5.1.02.01.0002', 'nama' => 'Belanja Jasa', 'tipe' => 'belanja', 'parent_id' => null, 'is_kas_leaf' => false],
            ['id' => 3, 'kode' => '5.2.02.01.0001', 'nama' => 'Belanja Modal Peralatan dan Mesin', 'tipe' => 'belanja', 'parent_id' => null, 'is_kas_leaf' => false],
        ]);
    }

    /** @param array<string, mixed> $filters */
    public function sumberDana(array $filters = []): SihandalCollection
    {
        return new SihandalCollection(items: [
            ['id' => 1, 'nama_sumber_dana' => 'Dana Alokasi Umum'],
            ['id' => 2, 'nama_sumber_dana' => 'Dana Alokasi Khusus'],
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
        return new SihandalCollection(items: self::TAHUN_ANGGARAN_ITEMS);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function syncDocument(array $payload): array
    {
        $number = is_string($payload['document_number'] ?? null) ? $payload['document_number'] : 'unknown';

        // Deterministic stand-in for the created SIHANDAL resource.
        return [
            'id' => 'sync-'.strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $number)),
            'document_number' => $number,
            'status' => 'recorded',
            'recorded_at' => now()->toIso8601String(),
        ];
    }
}
