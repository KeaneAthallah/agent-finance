<?php

use App\Services\Sihandal\FakeSihandalClient;
use App\Services\Sihandal\TahunAnggaranService;
use App\Support\SihandalCollection;

it('provides deterministic master data for offline development', function () {
    $client = new FakeSihandalClient;

    $opds = $client->opds();
    $rekenings = $client->rekenings();
    $years = $client->tahunAnggaran();

    expect($opds)->toBeInstanceOf(SihandalCollection::class)
        ->and($opds->items)->not->toBeEmpty()
        ->and($rekenings->items)->not->toBeEmpty()
        ->and($years->items)->not->toBeEmpty();
});

it('accepts filters without failing', function () {
    $client = new FakeSihandalClient;

    expect($client->programs(['opd_id' => 1])->items)->toBeArray()
        ->and($client->belanja(['tahun_anggaran_id' => 2])->items)->toBeArray();
});

it('filters belanja by the fiscal year id', function () {
    $client = new FakeSihandalClient;

    expect($client->belanja(['tahun_anggaran_id' => 2])->items)->toHaveCount(2)
        ->and($client->belanja(['tahun_anggaran_id' => 1])->items)->toBeEmpty();
});

it('resolves fiscal years to tahun_anggaran ids', function () {
    $service = new TahunAnggaranService;
    $client = new FakeSihandalClient;

    expect($service->idForYear(2026, $client))->toBe(2)
        ->and($service->idForYear(2025, $client))->toBe(1)
        ->and($service->idForYear(1999, $client))->toBeNull();
});
