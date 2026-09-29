<?php

use App\Services\Ai\FakeAIProvider;

it('extracts a document number and amount from OCR-like text', function () {
    $provider = new FakeAIProvider();

    $result = $provider->extractStructuredData(new App\Services\Ai\AiExtractionRequest(
        pages: [
            ['page' => 1, 'text' => "SURAT PERINTAH MEMBAYAR\nNomor: SPM-001/2026\nJumlah Rp125.000.000"],
        ],
        documentType: 'spm',
    ));

    expect($result->data['document_type'])->toBe('SPM')
        ->and($result->data['document_number'])->toBe('SPM-001/2026')
        ->and($result->confidence)->toBeGreaterThan(0.5);
});

it('falls back to low confidence for unrecognizable text', function () {
    $provider = new FakeAIProvider();

    $result = $provider->extractStructuredData(new App\Services\Ai\AiExtractionRequest(
        pages: [['page' => 1, 'text' => 'lorem ipsum dolor']],
        documentType: 'other',
    ));

    expect($result->confidence)->toBeLessThanOrEqual(0.5);
});

it('generates deterministic embeddings', function () {
    $provider = new FakeAIProvider();

    $first = $provider->generateEmbedding('same text', 'fake-model');
    $second = $provider->generateEmbedding('same text', 'fake-model');

    expect($first->vector)->toBe($second->vector)
        ->and($first->model)->toBe('fake-model');
});
