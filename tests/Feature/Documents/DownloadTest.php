<?php

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Opd;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('serves the file content through the download endpoint', function () {
    $disk = fakeDocumentsDisk();

    $opd = testOpd();
    $document = Document::factory()->withFile('%PDF-1.4 secret-financial-data')->for($opd, 'opd')->create();
    $user = testUser(UserRole::Verifier->value, $opd);

    $response = actingAs($user)->get(route('documents.download', $document));

    $response->assertOk();

    // Local-disk downloads stream their content; capture the stream.
    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    expect($content)->toContain('secret-financial-data');
});

it('blocks downloads for other opds', function () {
    $disk = fakeDocumentsDisk();

    $document = Document::factory()->withFile()->create();
    $otherOpd = Opd::factory()->create(['code' => '5.55.0']);
    $user = testUser(UserRole::Verifier->value, $otherOpd);

    actingAs($user)->get(route('documents.download', $document))->assertForbidden();
});

it('requires authentication for downloads', function () {
    $document = Document::factory()->create();

    get(route('documents.download', $document))->assertRedirect(route('login'));
});
