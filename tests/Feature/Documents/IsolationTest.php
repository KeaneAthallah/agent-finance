<?php

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Opd;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('scopes the index to the user opd', function () {
    $opdA = testOpd();
    $opdB = Opd::factory()->create(['code' => '8.88.0', 'name' => 'Other OPD']);

    $own = Document::factory()->for($opdA, 'opd')->create();
    Document::factory()->for($opdB, 'opd')->create();

    $user = testUser(UserRole::Verifier->value, $opdA);

    actingAs($user)->get(route('documents.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('documents/index')
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $own->id));
});

it('allows platform staff to see all opds documents', function () {
    Document::factory()->count(2)->create();

    $staff = testUser(UserRole::SuperAdmin->value, null);

    actingAs($staff)->get(route('documents.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('documents.data', 2));
});

it('blocks viewing another opds document', function () {
    $document = Document::factory()->create(); // default OPD
    $otherOpd = Opd::factory()->create(['code' => '7.77.0']);

    $user = testUser(UserRole::Verifier->value, $otherOpd);

    actingAs($user)->get(route('documents.show', $document))->assertForbidden();
});

it('allows viewing a document of the same opd', function () {
    $opd = testOpd();
    $document = Document::factory()->for($opd, 'opd')->create();
    $user = testUser(UserRole::Verifier->value, $opd);

    actingAs($user)->get(route('documents.show', $document))->assertOk();
});

it('blocks viewers from the documents area', function () {
    $user = testUser(UserRole::Viewer->value, testOpd());

    actingAs($user)->get(route('documents.index'))->assertForbidden();
});

it('blocks upload for viewers', function () {
    $user = testUser(UserRole::Viewer->value, testOpd());

    actingAs($user)->post(route('documents.store'), [])->assertForbidden();
});

it('prevents deleting documents of another opd', function () {
    $disk = fakeDocumentsDisk();

    $document = Document::factory()->withFile()->create();
    $otherOpd = Opd::factory()->create(['code' => '6.66.0']);
    $admin = testUser(UserRole::Admin->value, $otherOpd);

    actingAs($admin)->delete(route('documents.destroy', $document))->assertForbidden();

    expect(Document::query()->whereKey($document->id)->exists())->toBeTrue();
});

it('deletes own-opd documents as opd admin and removes the file', function () {
    $disk = fakeDocumentsDisk();

    $opd = testOpd();
    $document = Document::factory()->withFile()->for($opd, 'opd')->create();
    $admin = testUser(UserRole::Admin->value, $opd);

    actingAs($admin)->delete(route('documents.destroy', $document))->assertRedirect();

    expect(Document::query()->whereKey($document->id)->exists())->toBeFalse();

    Storage::disk($disk)->assertMissing($document->file_path);
});
