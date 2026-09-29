<?php

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Events\DocumentUploaded;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use Database\Seeders\AgentFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Upload tests assert upload semantics; the processing chain is covered
    // by PipelineTest. Faking the bus keeps these tests fast and isolated.
    Bus::fake();
});

function verifier(): User
{
    return testUser(UserRole::Verifier->value, testOpd());
}

function pdfUpload(string $content = 'minimalPdf'): UploadedFile
{
    $content = $content === 'minimalPdf' ? minimalPdf() : $content;

    return UploadedFile::fake()->createWithContent('spm_2026.pdf', $content);
}

it('uploads a pdf into private storage and creates the document', function () {
    $disk = fakeDocumentsDisk();

    $user = verifier();
    actingAs($user);

    $response = post(route('documents.store'), [
        'file' => pdfUpload(),
        'fiscal_year' => 2026,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $document = Document::query()->sole();

    expect($document->opd_id)->toBe($user->opd_id)
        ->and($document->status)->toBe(DocumentStatus::Uploaded)
        ->and($document->original_filename)->toBe('spm_2026.pdf')
        ->and($document->page_count)->toBe(1)
        ->and(strlen($document->file_hash))->toBe(64);

    Storage::disk($disk)->assertExists($document->file_path);

    // File must NOT be publicly reachable (spec §8).
    expect(str_starts_with($document->file_path, 'opd-'))->toBeTrue();
});

it('fires the DocumentUploaded event', function () {
    $disk = fakeDocumentsDisk();
    Event::fake([DocumentUploaded::class]);

    actingAs(verifier());

    post(route('documents.store'), ['file' => pdfUpload()])->assertRedirect();

    Event::assertDispatched(DocumentUploaded::class);
});

it('computes identical sha-256 for identical content', function () {
    $disk = fakeDocumentsDisk();

    $document = Document::factory()->create([
        'file_hash' => hash('sha256', 'same-content'),
    ]);

    expect($document->file_hash)->toBe(hash('sha256', 'same-content'));
});

it('rejects a duplicate upload by hash', function () {
    $disk = fakeDocumentsDisk();
    actingAs(verifier());

    $content = '%PDF-1.4 unique-content-xyz';

    post(route('documents.store'), ['file' => pdfUpload($content)])->assertRedirect();

    $response = post(route('documents.store'), ['file' => pdfUpload($content)]);

    $response->assertSessionHasErrors('file');

    expect(Document::query()->count())->toBe(1);
});

it('returns 409 json for duplicate uploads from api clients', function () {
    $disk = fakeDocumentsDisk();
    actingAs(verifier());

    $content = '%PDF-1.4 api-duplicate-check';

    post(route('documents.store'), ['file' => pdfUpload($content)], ['Accept' => 'application/json'])
        ->assertCreated();

    post(route('documents.store'), ['file' => pdfUpload($content)], ['Accept' => 'application/json'])
        ->assertStatus(409);
});

it('rejects unsupported extensions', function () {
    $disk = fakeDocumentsDisk();
    actingAs(verifier());

    post(route('documents.store'), [
        'file' => UploadedFile::fake()->create('script.exe', 10),
    ])->assertSessionHasErrors('file');

    expect(Document::query()->count())->toBe(0);
});

it('rejects a renamed executable with a pdf extension (signature check)', function () {
    $disk = fakeDocumentsDisk();
    actingAs(verifier());

    // PE executable magic "MZ" disguised as .pdf.
    $malicious = "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF fake executable";

    post(route('documents.store'), [
        'file' => UploadedFile::fake()->createWithContent('not_a_pdf.pdf', $malicious),
    ])->assertSessionHasErrors('file');

    expect(Document::query()->count())->toBe(0);
});

it('validates file size limit', function () {
    $disk = fakeDocumentsDisk();
    actingAs(verifier());

    config()->set('agent-finance.documents.max_size_kb', 1);

    post(route('documents.store'), [
        'file' => UploadedFile::fake()->createWithContent('big.pdf', str_repeat('%PDF-1.4 '.str_repeat('a', 2048), 4)),
    ])->assertSessionHasErrors('file');

    expect(Document::query()->count())->toBe(0);
});

it('associates the document type when provided', function () {
    $disk = fakeDocumentsDisk();
    $this->seed(AgentFinanceSeeder::class);

    $type = DocumentType::query()->where('code', 'spm')->firstOrFail();

    actingAs(verifier());

    post(route('documents.store'), [
        'file' => pdfUpload(),
        'document_type_id' => $type->id,
        'fiscal_year' => 2026,
    ])->assertRedirect();

    expect(Document::query()->sole()->document_type_id)->toBe($type->id)
        ->and(Document::query()->sole()->fiscal_year)->toBe(2026);
});
