<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\AgentFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function auditUser(): User
{
    return testUser(UserRole::Verifier->value, testOpd());
}

it('audits document upload as a human action with actor metadata', function () {
    fakeDocumentsDisk();
    $this->seed(AgentFinanceSeeder::class);

    actingAs(auditUser());

    post(route('documents.store'), [
        'file' => UploadedFile::fake()->createWithContent('spm.pdf', minimalPdf()),
        'fiscal_year' => 2026,
    ])->assertRedirect();

    $entry = AuditLog::query()->where('action', 'document.uploaded')->sole();

    expect($entry->actor_type)->toBe('human')
        ->and($entry->user_id)->not->toBeNull()
        ->and($entry->subject_type)->toBe('document')
        ->and($entry->properties['filename'])->toBe('spm.pdf')
        ->and($entry->properties['file_hash'])->toBeString();
});

it('audits document download', function () {
    fakeDocumentsDisk();
    $this->seed(AgentFinanceSeeder::class);

    $user = auditUser();
    actingAs($user);

    $document = Document::factory()->for($user->opd, 'opd')->withFile()->create();

    get(route('documents.download', $document))->assertOk();

    expect(AuditLog::query()->where('action', 'document.downloaded')->where('subject_id', $document->id)->exists())->toBeTrue();
});

it('audits document deletion before the row is removed', function () {
    fakeDocumentsDisk();
    $this->seed(AgentFinanceSeeder::class);

    $admin = testUser(UserRole::Admin->value, testOpd());
    actingAs($admin);

    $document = Document::factory()->for($admin->opd, 'opd')->withFile()->create();

    delete(route('documents.destroy', $document))->assertRedirect();

    $entry = AuditLog::query()->where('action', 'document.deleted')->sole();

    expect($entry->subject_type)->toBe('document')
        ->and($entry->subject_id)->toBe($document->id)
        ->and($entry->properties['file_hash'])->toBe($document->file_hash);
});

it('records system actor for validation routing', function () {
    fakeDocumentsDisk();
    $this->seed(AgentFinanceSeeder::class);

    actingAs(auditUser());

    // The PDF text carries all schema-required fields (number, date, fiscal
    // year, amount) so the fake extraction is clean and validation
    // auto-approves the document as a system actor.
    post(route('documents.store'), [
        'file' => UploadedFile::fake()->createWithContent('spm.pdf', minimalPdf('Nomor: SPM-001/2026 Tanggal: 15/03/2026 Tahun Anggaran 2026 Jumlah Rp125.000.000')),
    ])->assertRedirect();

    $document = Document::query()->sole();

    $validated = AuditLog::query()->where('action', 'document.validated')->sole();

    expect($validated->actor_type)->toBe('system')
        ->and($validated->user_id)->toBeNull()
        ->and(AuditLog::query()->where('action', 'document.auto_approved')->exists())->toBeTrue();
});
