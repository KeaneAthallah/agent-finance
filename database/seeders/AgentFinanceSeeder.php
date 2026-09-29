<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\DocumentType;
use App\Models\Opd;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AgentFinanceSeeder extends Seeder
{
    /**
     * Sample OPD codes follow common Indonesian local government naming;
     * these are editable admin data, not hardcoded behavior.
     *
     * @var array<int, array{code: string, name: string}>
     */
    private const OPDS = [
        ['code' => '4.01.0', 'name' => 'Dinas Pendidikan'],
        ['code' => '4.02.0', 'name' => 'Dinas Kesehatan'],
        ['code' => '1.01.0', 'name' => 'Sekretariat Daerah'],
        ['code' => '5.02.0', 'name' => 'Dinas Pekerjaan Umum'],
        ['code' => '2.16.0', 'name' => 'Badan Pengelola Keuangan dan Aset Daerah'],
        ['code' => '1.02.0', 'name' => 'Sekretariat DPRD'],
        ['code' => '4.05.0', 'name' => 'Dinas Sosial'],
        ['code' => '1.03.0', 'name' => 'Inspektorat Daerah'],
    ];

    private const ROLES = [
        ['slug' => 'super_admin', 'name' => 'Super Admin'],
        ['slug' => 'admin', 'name' => 'Administrator'],
        ['slug' => 'verifier', 'name' => 'Verifier'],
        ['slug' => 'viewer', 'name' => 'Viewer'],
    ];

    /**
     * Extensible document type catalog (spec §6). New types are admin data —
     * the AI classifier reads this catalog, not hardcoded logic.
     *
     * @var array<int, array{code: string, name: string, description?: string}>
     */
    private const DOCUMENT_TYPES = [
        ['code' => 'dpa', 'name' => 'DPA', 'description' => 'Dokumen Pelaksanaan Anggaran'],
        ['code' => 'rka', 'name' => 'RKA', 'description' => 'Rencana Kerja dan Anggaran'],
        ['code' => 'dpa_perubahan', 'name' => 'DPA Perubahan'],
        ['code' => 'spd', 'name' => 'SPD', 'description' => 'Surat Penyediaan Dana'],
        ['code' => 'spp', 'name' => 'SPP', 'description' => 'Surat Permintaan Pembayaran'],
        ['code' => 'spm', 'name' => 'SPM', 'description' => 'Surat Perintah Membayar'],
        ['code' => 'sp2d', 'name' => 'SP2D', 'description' => 'Surat Perintah Pencairan Dana'],
        ['code' => 'surat_permintaan_dana', 'name' => 'Surat Permintaan Dana'],
        ['code' => 'kwitansi', 'name' => 'Kwitansi'],
        ['code' => 'invoice', 'name' => 'Invoice'],
        ['code' => 'faktur', 'name' => 'Faktur'],
        ['code' => 'bukti_transfer', 'name' => 'Bukti Transfer'],
        ['code' => 'rekening_koran', 'name' => 'Rekening Koran'],
        ['code' => 'bku', 'name' => 'BKU', 'description' => 'Buku Kas Umum Daerah'],
        ['code' => 'laporan_keuangan', 'name' => 'Laporan Keuangan'],
        ['code' => 'laporan_realisasi', 'name' => 'Laporan Realisasi'],
        ['code' => 'dokumen_pengadaan', 'name' => 'Dokumen Pengadaan'],
        ['code' => 'kontrak', 'name' => 'Kontrak'],
        ['code' => 'surat_tugas', 'name' => 'Surat Tugas'],
        ['code' => 'nota_dinas', 'name' => 'Nota Dinas'],
        ['code' => 'other', 'name' => 'Other'],
    ];

    /**
     * Run the seeder.
     */
    public function run(): void
    {
        foreach (self::ROLES as $role) {
            Role::query()->firstOrCreate(['slug' => $role['slug']], ['name' => $role['name']]);
        }

        foreach (self::OPDS as $opd) {
            Opd::query()->firstOrCreate(['code' => $opd['code']], ['name' => $opd['name']]);
        }

        foreach (self::DOCUMENT_TYPES as $type) {
            DocumentType::query()->firstOrCreate(['code' => $type['code']], [
                'name' => $type['name'],
                'description' => $type['description'] ?? null,
            ]);
        }

        $adminRole = Role::query()->where('slug', UserRole::SuperAdmin->value)->first();
        $adminOpd = Opd::query()->where('code', '2.16.0')->first();

        User::query()->firstOrCreate(
            ['email' => 'admin@agentfinance.test'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'role_id' => $adminRole?->id,
                'opd_id' => $adminOpd?->id,
                'is_active' => true,
            ],
        );

        // Demo OPD users (password: password)
        $opdUsers = [
            ['email' => 'verifikator@agentfinance.test', 'name' => 'Verifikator Pendidikan', 'role' => 'verifier', 'opd' => '4.01.0'],
            ['email' => 'viewer@agentfinance.test', 'name' => 'Viewer Pendidikan', 'role' => 'viewer', 'opd' => '4.01.0'],
            ['email' => 'admin_opd@agentfinance.test', 'name' => 'Admin Dinas Kesehatan', 'role' => 'admin', 'opd' => '4.02.0'],
        ];

        foreach ($opdUsers as $u) {
            User::query()->firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make('password'),
                    'role_id' => Role::query()->where('slug', $u['role'])->value('id'),
                    'opd_id' => Opd::query()->where('code', $u['opd'])->value('id'),
                    'is_active' => true,
                ],
            );
        }
    }
}
