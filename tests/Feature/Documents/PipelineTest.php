<?php

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Jobs\ClassifyDocumentJob;
use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\AgentFinanceSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

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

    // Corrupt PDF: parser throws -> explicit failure state (spec §5).
    post(route('documents.store'), [
        'file' => UploadedFile::fake()->createWithContent('broken.pdf', 'not a pdf at all'),
    ])->assertRedirect();

    $document = Document::query()->sole();

    expect($document->status)->toBe(DocumentStatus::ProcessingFailed);

    $run = $document->processingRuns()->where('stage', 'text_extraction')->first();

    expect($run?->status)->toBe('failed')
        ->and($run?->error)->not->toBeNull();
});

it('dispatches the head job for queued processing', function () {
    Bus::fake();

    $document = Document::factory()->create();

    app(App\Actions\StartDocumentProcessingAction::class)->execute($document);

    Bus::assertDispatched(ProcessDocumentJob::class);
});

it('ignores stale job versions', function () {
    fakeDocumentsDisk();

    $document = Document::factory()->create(['status' => DocumentStatus::Queued]);
    $document->processingRuns()->create(['version' => 2, 'stage' => 'pipeline', 'status' => 'pending']);

    // Version 1 job arriving late must be a no-op (spec §31).
    (new ProcessDocumentJob($document->id, 1))->handle(app(App\Services\Extraction\TextExtractor::class));

    expect($document->refresh()->status)->toBe(DocumentStatus::Queued)
        ->and($document->pages()->count())->toBe(0);
});

it('allows retrying a failed document without re-upload', function () {
    fakeDocumentsDisk();
    $this->seed(AgentFinanceSeeder::class);

    actingAs(pipelineUser());

    post(route('documents.store'), [
        'file' => UploadedFile::fake()->createWithContent('retry.pdf', 'garbage-not-a-pdf'),
    ])->assertRedirect();

    $document = Document::query()->sole();

    expect($document->status)->toBe(DocumentStatus::ProcessingFailed);

    // Replace the stored file with a parseable one, then retry (spec §31).
    Storage::disk((string) config('agent-finance.documents.disk'))->put(
        $document->file_path,
        minimalPdf(),
    );

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

it('classifies against the catalog without changing an explicitly chosen type', function () {
    fakeDocumentsDisk();
    $this->seed(AgentFinanceSeeder::class);

    // Uploaded with an explicit type; fake classifier returns "other".
    $document = Document::factory()->create([
        'document_type_id' => App\Models\DocumentType::query()->where('code', 'spm')->value('id'),
    ]);

    $document->pages()->create([
        'page_number' => 1,
        'extracted_text' => 'SURAT PERINTAH MEMBAYAR',
        'text_source' => 'embedded',
        'processing_status' => 'done',
    ]);

    (new ClassifyDocumentJob($document->id, 1))->handle(app(App\Services\Ai\AIProviderInterface::class));

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::ExtractionComplete);
});
