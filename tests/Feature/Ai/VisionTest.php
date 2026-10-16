<?php

use App\Models\Document;
use App\Models\DocumentPage;
use App\Support\DocumentPagePayloads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function visionPage(Document $document, array $attributes = []): DocumentPage
{
    return $document->pages()->create(array_merge([
        'page_number' => 1,
        'extracted_text' => 'Nomor: SPM-1',
        'text_source' => 'ocr',
        'processing_status' => 'done',
    ], $attributes));
}

it('attaches no image and keeps text when vision is disabled', function () {
    config(['agent-finance.ai.vision.enabled' => false]);

    $disk = fakeDocumentsDisk();
    Storage::disk($disk)->put('pages/1.png', 'binary-image');

    $document = Document::factory()->create();
    visionPage($document, ['image_path' => 'pages/1.png']);

    $pages = DocumentPagePayloads::for($document);

    expect($pages)->toHaveCount(1)
        ->and($pages[0])->not->toHaveKey('image')
        ->and($pages[0]['text'])->toBe('Nomor: SPM-1');
});

it('attaches a base64 data URI when vision is enabled', function () {
    config(['agent-finance.ai.vision.enabled' => true]);

    $disk = fakeDocumentsDisk();
    Storage::disk($disk)->put('pages/1.png', 'binary-image');

    $document = Document::factory()->create();
    visionPage($document, ['image_path' => 'pages/1.png']);

    $pages = DocumentPagePayloads::for($document);

    expect($pages[0])->toHaveKey('image')
        ->and($pages[0]['image'])->toStartWith('data:')
        ->and($pages[0]['image'])->toContain(base64_encode('binary-image'));
});

it('falls back to text when the page image is missing', function () {
    config(['agent-finance.ai.vision.enabled' => true]);

    fakeDocumentsDisk();

    $document = Document::factory()->create();
    visionPage($document, ['image_path' => 'pages/missing.png']);

    $pages = DocumentPagePayloads::for($document);

    expect($pages[0])->not->toHaveKey('image')
        ->and($pages[0]['text'])->toBe('Nomor: SPM-1');
});
