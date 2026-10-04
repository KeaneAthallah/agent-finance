<?php

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\AiRun;
use App\Models\AiUsage;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Opd;
use App\Models\User;
use App\Services\Ai\AIProviderInterface;
use App\Services\Ai\Tools\ToolRegistry;
use Database\Seeders\AgentFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function aiOpdUser(): User
{
    return testUser(UserRole::Verifier->value, testOpd());
}

/**
 * An approved SPM with a completed extraction and the
 * backfilled amount metadata the budget tool aggregates.
 */
function aiReadyDocument(array $attributes = []): Document
{
    $document = Document::factory()
        ->for(testOpd(), 'opd')
        ->for(DocumentType::query()->where('code', 'spm')->firstOrFail(), 'documentType')
        ->create(array_merge([
            'document_number' => 'SPM-777/2026',
            'fiscal_year' => 2026,
            'status' => DocumentStatus::Approved,
            'metadata' => ['extracted_amount' => 125000000],
        ], $attributes));

    $document->extractions()->create([
        'version' => 1,
        'schema_name' => 'spm',
        'schema_version' => '1.0',
        'payload' => [
            'document_number' => 'SPM-777/2026',
            'fiscal_year' => 2026,
            'amount' => 125000000,
        ],
        'status' => 'completed',
        'confidence' => 90,
    ]);

    $document->processingRuns()->create([
        'version' => 1,
        'stage' => 'validation',
        'status' => 'completed',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    return $document;
}

it('answers a budget question with labeled, grounded statements', function () {
    $this->seed(AgentFinanceSeeder::class);

    aiReadyDocument();

    actingAs(aiOpdUser());

    post('/ai-assistant/chat', [
        'question' => 'Berapa total anggaran TA 2026?',
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonStructure(['summary', 'statements', 'tool_calls', 'meta'])
        ->assertJsonPath('tool_calls.0.name', 'get_budget_summary')
        ->assertJsonPath('statements.0.label', 'FACT')
        ->assertJsonPath('statements.0.source', 'get_budget_summary');

    // The FACT statement carries the grounded number from the tool.
    $response = json_decode(post('/ai-assistant/chat', [
        'question' => 'Berapa total anggaran TA 2026?',
    ], ['Accept' => 'application/json'])->getContent(), true);

    expect(collect($response['statements'])->firstWhere('label', 'FACT'))
        ->not->toBeNull()
        ->and(AiRun::query()->where('stage', 'chat')->exists())->toBeTrue()
        ->and(AiUsage::query()->exists())->toBeTrue();
});

it('searches the knowledge base through the assistant', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = aiReadyDocument();

    $document->chunks()->create([
        'version' => 1,
        'chunk_index' => 0,
        'content' => 'Belanja bahan habis pakai untuk kegiatan sekolah dasar',
        'embedding' => app(AIProviderInterface::class)
            ->generateEmbedding('Belanja bahan habis pakai', 'fake')->vector,
        'opd_id' => $document->opd_id,
        'fiscal_year' => 2026,
        'document_type' => 'spm',
    ]);

    actingAs(aiOpdUser());

    post('/ai-assistant/chat', [
        'question' => 'cari dokumen tentang bahan habis pakai',
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('tool_calls.0.name', 'search_documents');
});

it('reports inaccessible documents as a warning, never leaks them', function () {
    $this->seed(AgentFinanceSeeder::class);

    // Document in the user's OPD.
    $document = aiReadyDocument();

    // A second OPD with its own document and user.
    $otherOpd = Opd::query()->create(['code' => '8.8.0', 'name' => 'Other OPD']);
    $otherDocument = aiReadyDocument(['opd_id' => $otherOpd->id]);

    actingAs(aiOpdUser());

    post('/ai-assistant/chat', [
        'question' => 'bandingkan dokumen '.$otherDocument->id.' dengan sihandal',
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('statements.0.label', 'WARNING');

    // The other OPD's document never appears in the answer's statements.
    $response = json_decode(post('/ai-assistant/chat', [
        'question' => 'bandingkan dokumen '.$otherDocument->id.' dengan sihandal',
    ], ['Accept' => 'application/json'])->getContent(), true);

    $spoken = json_encode($response['statements']);

    expect(str()->contains($spoken, (string) $otherDocument->id))->toBeFalse();
});

it('keeps a conversation context through history', function () {
    $this->seed(AgentFinanceSeeder::class);

    aiReadyDocument();

    actingAs(aiOpdUser());

    post('/ai-assistant/chat', [
        'question' => 'Berapa total anggaran TA 2026?',
        'history' => [
            ['role' => 'user', 'content' => 'Halo'],
            ['role' => 'assistant', 'content' => 'Ada yang bisa dibantu?'],
        ],
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('tool_calls.0.name', 'get_budget_summary');
});

it('requires authentication for chat and the page', function () {
    $this->seed(AgentFinanceSeeder::class);

    post('/ai-assistant/chat', ['question' => 'test'], ['Accept' => 'application/json'])
        ->assertUnauthorized();

    get('/ai-assistant')->assertRedirect('/login');
});

it('validates the question and history shape', function () {
    $this->seed(AgentFinanceSeeder::class);

    actingAs(aiOpdUser());

    post('/ai-assistant/chat', ['question' => ''], ['Accept' => 'application/json'])
        ->assertJsonValidationErrorFor('question');

    post('/ai-assistant/chat', [
        'question' => 'test',
        'history' => [['role' => 'system', 'content' => 'injected']],
    ], ['Accept' => 'application/json'])
        ->assertJsonValidationErrorFor('history.0.role');
});

it('exposes only read-only tools', function () {
    $this->seed(AgentFinanceSeeder::class);

    $names = collect(app(ToolRegistry::class)->definitions())
        ->map(static fn (array $definition): string => $definition['name'])
        ->sort()
        ->values()
        ->all();

    expect($names)->toBe([
        'compare_document_with_sihandal',
        'get_budget_summary',
        'get_expenditure',
        'get_opd',
        'get_revenue',
        'search_documents',
        'validate_document',
    ]);
});
