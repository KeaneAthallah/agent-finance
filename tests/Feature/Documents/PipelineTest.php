<?php

use App\Actions\StartDocumentProcessingAction;
use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Jobs\ClassifyDocumentJob;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\User;
use App\Services\Ai\AIProviderInterface;
use App\Services\Extraction\TextExtractor;
use Database\Seeders\AgentFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function pipelineUser(): User
{
    return testUser(UserRole::Verifier->value, testOpd());
}

function pipelinePdf(string $text = 'Nomor: SPM-001/2026 Jumlah Rp125.000.000'): UploadedFile
{
    return UploadedFile::fake()->createWithContent('bku_2026.pdf', minimalPdf($text));
}

it('auto-starts processing after upload and runs the pipeline to extraction_complete', function () {
    fakeDocumentsDisk();
    $this->seed(AgentFinanceSeeder::class);

    actingAs(pipelineUser());

    post(route('documents.store'), ['file' => pipelinePdf()])->assertRedirect();

    $document = Document::query()->sole();

    // QUEUE_CONNECTION=sync: the whole chain ran inline.
    expect($document->refresh()->status)->toBe(DocumentStatus::ExtractionComplete)
        ->and($document->page_count)->toBe(1)
        ->and($document->pages()->where('processing_status', 'done')->count())->toBe(1);

    $stages = $document->processingRuns()->pluck('status', 'stage');

    expect($stages->get('text_extraction'))->toBe('completed')
        ->and($stages->get('ocr'))->toBe('completed')
        ->and($stages->get('classification'))->toBe('completed');
});

it('records the extracted text on the page row', function () {
    fakeDocumentsDisk();
    $this->seed(AgentFinanceSeeder::class);

    actingAs(pipelineUser());

    $text = 'SURAT PERINTAH MEMBAYAR Nomor: SPM-777/2026';

    post(route('documents.store'), ['file' => pipelinePdf($text)])->assertRedirect();

    $page = Document::query()->sole()->pages()->first();

    expect($page)->not->toBeNull()
        ->and($page->text_source)->toBe('embedded')
        ->and($page->extracted_text)->toContain('SPM-777/2026');
});

it('marks document as processing_failed when text extraction fails', function () {
    fakeDocumentsDisk();
    $this->seed(AgentFinanceSeeder::class);

    actingAs(pipelineUser());

    $document = Document::factory()->create();
    Storage::disk((string) config('agent-finance.documents.disk'))->put($document->file_path, 'not a pdf at all');

    // Mirror what StartDocumentProcessingAction does before dispatching.
    $document->processingRuns()->create(['version' => 1, 'stage' => 'pipeline', 'status' => 'pending']);

    // Corrupt stored file: parser throws -> explicit failure state (spec §5).
    // Invoked directly (not via HTTP) so the exception surfaces here.
    (new ProcessDocumentJob($document->id, 1))->handle(app(TextExtractor::class));
})->throws(RuntimeException::class, 'PDF parsing failed');

it('dispatches the head job for queued processing', function () {
    Bus::fake();

    $document = Document::factory()->create();

    app(StartDocumentProcessingAction::class)->execute($document);

    Bus::assertDispatched(ProcessDocumentJob::class);
});

it('ignores stale job versions', function () {
    fakeDocumentsDisk();

    $document = Document::factory()->create(['status' => DocumentStatus::Queued]);
    $document->processingRuns()->create(['version' => 2, 'stage' => 'pipeline', 'status' => 'pending']);

    // Version 1 job arriving late must be a no-op (spec §31).
    (new ProcessDocumentJob($document->id, 1))->handle(app(TextExtractor::class));

    expect($document->refresh()->status)->toBe(DocumentStatus::Queued)
        ->and($document->pages()->count())->toBe(0);
});

it('allows retrying a failed document without re-upload', function () {
    fakeDocumentsDisk();
    $this->seed(AgentFinanceSeeder::class);

    $user = pipelineUser();
    actingAs($user);

    // Document must belong to the acting user's OPD to pass authorization.
    $document = Document::factory()->for($user->opd, 'opd')->create();
    Storage::disk((string) config('agent-finance.documents.disk'))->put($document->file_path, 'broken-content');

    $document->processingRuns()->create(['version' => 1, 'stage' => 'pipeline', 'status' => 'pending']);

    // First run fails (corrupt file).
    try {
        (new ProcessDocumentJob($document->id, 1))->handle(app(TextExtractor::class));
    } catch (RuntimeException) {
        // expected
    }

    expect($document->refresh()->status)->toBe(DocumentStatus::ProcessingFailed);

    // Fix the stored file, then retry via the endpoint (spec §31).
    Storage::disk((string) config('agent-finance.documents.disk'))->put($document->file_path, minimalPdf());

    post(route('documents.process', $document))->assertRedirect();

    expect($document->refresh()->status)->toBe(DocumentStatus::ExtractionComplete)
        ->and((int) $document->processingRuns()->max('version'))->toBe(2);
});

it('exposes pipeline state to the frontend', function () {
    fakeDocumentsDisk();
    $this->seed(AgentFinanceSeeder::class);

    actingAs(pipelineUser());

    post(route('documents.store'), ['file' => pipelinePdf()])->assertRedirect();

    $document = Document::query()->sole();

    actingAs(pipelineUser())
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('documents/show')
            ->has('pipeline')
            ->has('pages'));
});

it('classifies against the catalog and finishes the pipeline', function () {
    fakeDocumentsDisk();
    $this->seed(AgentFinanceSeeder::class);

    $document = Document::factory()->create();

    $document->processingRuns()->create(['version' => 1, 'stage' => 'pipeline', 'status' => 'pending']);

    $document->pages()->create([
        'page_number' => 1,
        'extracted_text' => 'SURAT PERINTAH MEMBAYAR',
        'text_source' => 'embedded',
        'processing_status' => 'done',
    ]);

    (new ClassifyDocumentJob($document->id, 1))->handle(app(AIProviderInterface::class));

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::ExtractionComplete);
});
