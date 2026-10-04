<?php

use App\Services\Ai\Chunker;

it('splits text into sentence-aligned chunks under the limit', function () {
    $sentences = [];

    for ($i = 0; $i < 30; $i++) {
        $sentences[] = "Kalimat nomor {$i} ini adalah contoh kalimat yang cukup panjang untuk keperluan pengujian.";
    }

    $chunks = (new Chunker)->chunk(implode(' ', $sentences));

    expect($chunks)->not->toBeEmpty()
        ->and(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect(mb_strlen($chunk['content']))->toBeLessThanOrEqual(Chunker::MAX_CHUNK_CHARS)
            ->and($chunk['index'])->toBeInt();
    }

    // Sequential, zero-based indexes.
    expect(array_column($chunks, 'index'))->toBe(range(0, count($chunks) - 1));
});

it('returns no chunks for blank text', function () {
    expect((new Chunker)->chunk('   '))->toBe([])
        ->and((new Chunker)->chunk(''))->toBe([]);
});

it('hard-splits a single oversized sentence', function () {
    $long = str_repeat('a', Chunker::MAX_CHUNK_CHARS * 2 + 50);

    $chunks = (new Chunker)->chunk($long);

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect(mb_strlen($chunk['content']))->toBeLessThanOrEqual(Chunker::MAX_CHUNK_CHARS);
    }

    // No data is lost.
    expect(implode('', array_column($chunks, 'content')))->toBe($long);
});

it('keeps newlines as sentence boundaries', function () {
    $chunks = (new Chunker)->chunk("Kalimat pertama.\nKalimat kedua.");

    expect(count($chunks))->toBe(1)
        ->and($chunks[0]['content'])->toBe('Kalimat pertama. Kalimat kedua.');
});
