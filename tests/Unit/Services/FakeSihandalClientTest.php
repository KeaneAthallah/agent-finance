<?php

use App\Services\Sihandal\FakeSihandalClient;
use App\Support\SihandalCollection;

it('provides deterministic master data for offline development', function () {
    $client = new FakeSihandalClient();

    $opds = $client->opds();
    $rekenings = $client->rekenings();
    $years = $client->tahunAnggaran();

    expect($opds)->toBeInstanceOf(SihandalCollection::class)
        ->and($opds->items)->not->toBeEmpty()
        ->and($rekenings->items)->not->toBeEmpty()
        ->and($years->items)->not->toBeEmpty();
});

it('accepts filters without failing', function () {
    $client = new FakeSihandalClient();

    expect($client->programs(['opd_id' => 1])->items)->toBeArray()
        ->and($client->belanja(['fiscal_year' => 2026])->items)->toBeArray();
});
