<?php

use App\Models\User;
use App\Services\Ai\Tools\GetSihandalDataTool;
use App\Services\Sihandal\FakeSihandalClient;
use App\Services\Sihandal\TahunAnggaranService;
use InvalidArgumentException;
use RuntimeException;

it('lists every SIHANDAL resource', function () {
    $tool = new GetSihandalDataTool(new FakeSihandalClient, new TahunAnggaranService);
    $user = new User;

    foreach (['opds', 'programs', 'kegiatan', 'sub_kegiatan', 'belanja', 'rekenings', 'sumber_dana', 'penerimaan', 'pengeluaran', 'tahun_anggaran'] as $resource) {
        $result = $tool->execute(['resource' => $resource], $user);

        expect($result['resource'])->toBe($resource)
            ->and($result['items'])->toBeArray();
    }
});

it('resolves the fiscal year for year-scoped resources', function () {
    $tool = new GetSihandalDataTool(new FakeSihandalClient, new TahunAnggaranService);

    $result = $tool->execute(['resource' => 'belanja', 'fiscal_year' => 2026], new User);

    expect($result['resource'])->toBe('belanja')
        ->and($result['count'])->toBe(2);
});

it('rejects an unknown resource', function () {
    $tool = new GetSihandalDataTool(new FakeSihandalClient, new TahunAnggaranService);

    $tool->execute(['resource' => 'transaksi'], new User);
})->throws(InvalidArgumentException::class, 'Unknown resource');

it('rejects fiscal_year on resources that are not year-scoped', function () {
    $tool = new GetSihandalDataTool(new FakeSihandalClient, new TahunAnggaranService);

    $tool->execute(['resource' => 'programs', 'fiscal_year' => 2026], new User);
})->throws(InvalidArgumentException::class, 'fiscal_year does not apply');

it('rejects opd_id on resources that have no per-OPD rows', function () {
    $tool = new GetSihandalDataTool(new FakeSihandalClient, new TahunAnggaranService);

    $tool->execute(['resource' => 'rekenings', 'opd_id' => 1], new User);
})->throws(InvalidArgumentException::class, 'opd_id does not apply');

it('fails loudly for a year SIHANDAL does not have', function () {
    $tool = new GetSihandalDataTool(new FakeSihandalClient, new TahunAnggaranService);

    $tool->execute(['resource' => 'kegiatan', 'fiscal_year' => 1999], new User);
})->throws(RuntimeException::class, 'Fiscal year 1999 is not available in SIHANDAL.');
