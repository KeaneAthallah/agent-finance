<?php

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Jobs\ExtractFinancialDataJob;
use App\Models\AiPrompt;
use App\Models\AiRun;
use App\Models\AiUsage;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\Ai\AiExtractionResult;
use App\Services\Ai\AIProviderInterface;
use App\Services\Ai\PromptRegistry;
use App\Services\Extraction\ExtractionSchemaRegistry;
use Database\Seeders\AgentFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function extractionUser(): User
{
    return testUser(UserRole::Verifier->value, testOpd());
}

function extractionSpmDocument(): Document
{
    $document = Document::factory()
        ->for(DocumentType::query()->where('code', 'spm')->firstOrFail(), 'documentType')
        ->create();

    // Mirror StartDocumentProcessingAction: the job's version guard requires
    // a pipeline run whose version matches the job's.
    $document->processingRuns()->create(['version' => 1, 'stage' => 'pipeline', 'status' => 'pending']);

    $document->pages()->create([
        'page_number' => 1,
        'extracted_text' => 'SURAT PERINTAH MEMBAYAR Nomor: SPM-777/2026 Jumlah Rp125.000.000 Tanggal 15/03/2026',
        'text_source' => 'embedded',
        'processing_status' => 'done',
    ]);

    return $document;
}

it('runs extraction after classification in the sync pipeline', function () {
    fakeDocumentsDisk();
    $this->seed(AgentFinanceSeeder::class);

    actingAs(extractionUser());

    post(route('documents.store'), [
        'file' => UploadedFile::fake()->createWithContent(
            'spm.pdf',
            minimalPdf('SURAT PERINTAH MEMBAYAR Nomor: SPM-777/2026 Jumlah Rp125.000.000'),
        ),
    ])->assertRedirect();

    $document = Document::query()->sole();

    // QUEUE_CONNECTION=sync: classification dispatched extraction inline.
    expect($document->refresh()->status)->toBe(DocumentStatus::ExtractionComplete);

    $extraction = $document->extractions()->first();

    expect($extraction)->not->toBeNull()
        ->and($extraction->status)->toBe('completed')
        ->and($extraction->schema_name)->toBe('spm')
        ->and($extraction->fields()->count())->toBeGreaterThan(0);
});

it('stores per-field confidence and evidence', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = extractionSpmDocument();

    (new ExtractFinancialDataJob($document->id, 1))->handle(
        app(AIProviderInterface::class),
        app(ExtractionSchemaRegistry::class),
        app(PromptRegistry::class),
    );

    $extraction = $document->extractions()->first();
    $numberField = $extraction->fields()->where('field', 'document_number')->first();
    $amountField = $extraction->fields()->where('field', 'amount')->first();

    expect($numberField)->not->toBeNull()
        ->and($numberField->value)->toBe('SPM-777/2026')
        ->and($numberField->confidence)->toBeGreaterThanOrEqual(85)
        ->and($numberField->evidence)->not->toBeNull()
        ->and($numberField->evidence['page'])->toBe(1)
        ->and($amountField->value)->toBe('125000000')
        ->and($amountField->confidence)->toBeGreaterThanOrEqual(85);
});

it('writes an ai_run and ai_usage row per extraction call', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = extractionSpmDocument();

    (new ExtractFinancialDataJob($document->id, 1))->handle(
        app(AIProviderInterface::class),
        app(ExtractionSchemaRegistry::class),
        app(PromptRegistry::class),
    );

    $run = AiRun::query()->where('document_id', $document->id)->sole();

    expect($run->stage)->toBe('extraction')
        ->and($run->status)->toBe('completed')
        ->and($run->prompt_name)->toBe('builtin')
        ->and(AiUsage::query()->where('ai_run_id', $run->id)->exists())->toBeTrue();
});

it('records the prompt version used (spec 33)', function () {
    $this->seed(AgentFinanceSeeder::class);

    AiPrompt::query()->create([
        'name' => 'financial_extraction',
        'version' => '2.0',
        'system_prompt' => 'Government extraction rules v2.',
        'is_active' => true,
    ]);

    $document = extractionSpmDocument();

    (new ExtractFinancialDataJob($document->id, 1))->handle(
        app(AIProviderInterface::class),
        app(ExtractionSchemaRegistry::class),
        app(PromptRegistry::class),
    );

    $run = AiRun::query()->where('document_id', $document->id)->sole();

    expect($run->prompt_name)->toBe('financial_extraction')
        ->and($run->prompt_version)->toBe('2.0')
        ->and($run->prompt_id)->not->toBeNull();
});

it('resolves the spm schema with spm-specific fields', function () {
    $registry = app(ExtractionSchemaRegistry::class);

    $schema = $registry->forType('spm');
    $names = collect($schema->fields)->pluck('name');

    expect($names)->toContain('document_number')
        ->and($names)->toContain('spm_kind')
        ->and($names)->toContain('spp_number');

    $generic = $registry->forType('unknown_type');
    $genericNames = collect($generic->fields)->pluck('name');

    expect($genericNames)->toContain('document_number')
        ->and($genericNames)->toContain('amount')
        ->and($genericNames)->not->toContain('spm_kind');
});

it('keeps extraction history per processing version (spec 38)', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = extractionSpmDocument();

    foreach ([1, 2] as $version) {
        // Each extraction pass runs under its own pipeline version (§38);
        // the job's stale guard requires the matching run row.
        $document->processingRuns()->create(['version' => $version, 'stage' => 'pipeline', 'status' => 'pending']);

        (new ExtractFinancialDataJob($document->id, $version))->handle(
            app(AIProviderInterface::class),
            app(ExtractionSchemaRegistry::class),
            app(PromptRegistry::class),
        );
    }

    expect($document->extractions()->count())->toBe(2)
        ->and($document->extractions()->where('version', 1)->exists())->toBeTrue()
        ->and($document->extractions()->where('version', 2)->exists())->toBeTrue();
});

it('marks extraction failed when the provider throws', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = extractionSpmDocument();

    $provider = Mockery::mock(AIProviderInterface::class);
    $provider->shouldReceive('extractStructuredData')
        ->once()
        ->andThrow(new RuntimeException('provider exploded'));

    try {
        (new ExtractFinancialDataJob($document->id, 1))->handle(
            $provider,
            app(ExtractionSchemaRegistry::class),
            app(PromptRegistry::class),
        );
        $this->fail('Expected the provider exception to surface.');
    } catch (RuntimeException) {
        // expected (spec §5: explicit failure, never silent)
    }

    $extraction = $document->extractions()->first();

    expect($document->refresh()->status)->toBe(DocumentStatus::AiFailed)
        ->and($extraction->status)->toBe('failed')
        ->and($extraction->error)->toBe('provider exploded');

    $run = AiRun::query()->where('document_id', $document->id)->sole();

    expect($run->status)->toBe('failed')
        ->and($run->error)->toBe('provider exploded');
});

it('ignores stale extraction retries (version guard)', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = extractionSpmDocument();

    // Latest pipeline version is 2; a late retry of version 1 must no-op.
    $document->processingRuns()->create(['version' => 2, 'stage' => 'pipeline', 'status' => 'pending']);

    (new ExtractFinancialDataJob($document->id, 1))->handle(
        app(AIProviderInterface::class),
        app(ExtractionSchemaRegistry::class),
        app(PromptRegistry::class),
    );

    expect($document->extractions()->count())->toBe(0)
        ->and(AiRun::query()->where('document_id', $document->id)->count())->toBe(0);
});

it('surfaces the extraction on the detail page', function () {
    fakeDocumentsDisk();
    $this->seed(AgentFinanceSeeder::class);

    actingAs(extractionUser());

    post(route('documents.store'), [
        'file' => UploadedFile::fake()->createWithContent(
            'spm.pdf',
            minimalPdf('SURAT PERINTAH MEMBAYAR Nomor: SPM-777/2026'),
        ),
    ])->assertRedirect();

    $document = Document::query()->sole();

    actingAs(extractionUser())
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('documents/show')
            ->has('extraction')
            ->has('extraction.fields'));
});

it('records token usage on the run when the provider reports it', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = extractionSpmDocument();

    $provider = Mockery::mock(AIProviderInterface::class);
    $provider->shouldReceive('extractStructuredData')
        ->once()
        ->andReturn(new AiExtractionResult(
            data: ['document_number' => 'SPM-777/2026'],
            fields: [
                'document_number' => [
                    'value' => 'SPM-777/2026',
                    'confidence' => 0.93,
                    'source' => ['page' => 1, 'source_text' => 'Nomor: SPM-777/2026'],
                ],
            ],
            confidence: 0.93,
            meta: ['provider' => 'openai', 'input_tokens' => 812, 'output_tokens' => 94],
        ));

    (new ExtractFinancialDataJob($document->id, 1))->handle(
        $provider,
        app(ExtractionSchemaRegistry::class),
        app(PromptRegistry::class),
    );

    $run = AiRun::query()->where('document_id', $document->id)->sole();

    expect($run->input_tokens)->toBe(812)
        ->and($run->output_tokens)->toBe(94);

    $usage = AiUsage::query()->where('ai_run_id', $run->id)->sole();

    expect($usage->input_tokens)->toBe(812)
        ->and($usage->output_tokens)->toBe(94);

    $field = $document->extractions()->first()->fields()->where('field', 'document_number')->sole();

    expect($field->confidence)->toBe(93)
        ->and($field->evidence['source_text'])->toBe('Nomor: SPM-777/2026');
});
