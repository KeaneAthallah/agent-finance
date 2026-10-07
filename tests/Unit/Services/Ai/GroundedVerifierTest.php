<?php

use App\Services\Ai\ChatStatement;
use App\Services\Ai\GroundedVerifier;

it('keeps facts and calculations that name an executed tool', function () {
    $statements = [
        new ChatStatement(ChatStatement::FACT, 'Total anggaran Rp1 miliar.', 'get_budget_summary'),
        new ChatStatement(ChatStatement::CALCULATION, 'Selisihnya Rp5 juta.', 'get_budget_summary'),
    ];

    $verified = (new GroundedVerifier)->verify($statements, ['get_budget_summary']);

    expect($verified[0]->label)->toBe(ChatStatement::FACT)
        ->and($verified[1]->label)->toBe(ChatStatement::CALCULATION);
});

it('downgrades facts that cite a tool which was never executed', function () {
    $statements = [
        new ChatStatement(ChatStatement::FACT, 'PAD naik 12%.', 'get_revenue'),
    ];

    $verified = (new GroundedVerifier)->verify($statements, ['get_budget_summary']);

    expect($verified[0]->label)->toBe(ChatStatement::AI_INTERPRETATION)
        ->and($verified[0]->source)->toBe('get_revenue');
});

it('downgrades calculations without any source', function () {
    $statements = [
        new ChatStatement(ChatStatement::CALCULATION, 'Kenaikan diperkirakan 2 miliar.'),
    ];

    $verified = (new GroundedVerifier)->verify($statements, ['get_budget_summary']);

    expect($verified[0]->label)->toBe(ChatStatement::AI_INTERPRETATION);
});

it('leaves warnings and existing interpretations untouched', function () {
    $statements = [
        new ChatStatement(ChatStatement::WARNING, 'Data SIHANDAL tidak dapat diakses.', 'get_sihandal_data'),
        new ChatStatement(ChatStatement::AI_INTERPRETATION, 'Tren positif.', null),
    ];

    $verified = (new GroundedVerifier)->verify($statements, ['get_sihandal_data']);

    expect($verified[0]->label)->toBe(ChatStatement::WARNING)
        ->and($verified[1]->label)->toBe(ChatStatement::AI_INTERPRETATION);
});

it('keeps facts grounded in any of several executed tools', function () {
    $statements = [
        new ChatStatement(ChatStatement::FACT, 'Belanja terpusat.', 'get_sihandal_data'),
        new ChatStatement(ChatStatement::FACT, 'Dokumen cocok.', 'validate_document'),
    ];

    $verified = (new GroundedVerifier)->verify(
        $statements,
        ['get_sihandal_data', 'search_documents', 'validate_document'],
    );

    expect($verified[0]->label)->toBe(ChatStatement::FACT)
        ->and($verified[1]->label)->toBe(ChatStatement::FACT);
});
