<?php

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Opd;
use App\Models\User;
use App\Services\Ai\Tools\GetDocumentTextTool;
use Database\Seeders\AgentFinanceSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function documentTextUser(): User
{
    return testUser(UserRole::Verifier->value, testOpd());
}

it('returns the full page text and the latest extraction', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = Document::factory()
        ->for(testOpd(), 'opd')
        ->for(DocumentType::query()->where('code', 'spm')->firstOrFail(), 'documentType')
        ->create(['document_number' => 'SPM-777/2026', 'fiscal_year' => 2026]);

    $document->pages()->create(['page_number' => 1, 'extracted_text' => 'Halaman satu', 'text_source' => 'embedded', 'processing_status' => 'done']);
    $document->pages()->create(['page_number' => 2, 'extracted_text' => 'Halaman dua', 'text_source' => 'ocr', 'processing_status' => 'done']);

    $document->extractions()->create([
        'version' => 1,
        'schema_name' => 'spm',
        'schema_version' => '1.0',
        'payload' => ['amount' => 125000000],
        'status' => 'completed',
        'confidence' => 90,
    ]);

    $result = (new GetDocumentTextTool)->execute(['document_id' => $document->id], documentTextUser());

    expect($result['document_number'])->toBe('SPM-777/2026')
        ->and($result['page_count'])->toBe(2)
        ->and($result['text'])->toContain('--- Page 1 ---')
        ->and($result['text'])->toContain('Halaman dua')
        ->and($result['extraction']['amount'])->toBe(125000000)
        ->and($result['refs'])->toBe([['type' => 'document', 'id' => $document->id]]);
});

it('scopes to the user OPD and never leaks another OPD document', function () {
    $this->seed(AgentFinanceSeeder::class);

    $otherOpd = Opd::query()->create(['code' => '7.7.0', 'name' => 'Other OPD']);
    $other = Document::factory()->for($otherOpd, 'opd')->create();

    (new GetDocumentTextTool)->execute(['document_id' => $other->id], documentTextUser());
})->throws(ModelNotFoundException::class);

it('requires a document_id', function () {
    (new GetDocumentTextTool)->execute([], new User);
})->throws(InvalidArgumentException::class, 'document_id is required.');

it('reads a document through the assistant', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = Document::factory()
        ->for(testOpd(), 'opd')
        ->for(DocumentType::query()->where('code', 'spm')->firstOrFail(), 'documentType')
        ->create(['document_number' => 'SPM-777/2026']);

    $document->pages()->create(['page_number' => 1, 'extracted_text' => 'Halaman satu', 'text_source' => 'embedded', 'processing_status' => 'done']);

    actingAs(documentTextUser());

    post('/ai-assistant/chat', [
        'question' => 'isi lengkap dokumen '.$document->id,
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('tool_calls.0.name', 'get_document_text')
        ->assertJsonPath('statements.0.label', 'FACT')
        ->assertJsonPath('statements.0.source', 'get_document_text');
});
