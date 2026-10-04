<?php

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Jobs\GenerateEmbeddingsJob;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Opd;
use App\Models\User;
use App\Services\Ai\AIProviderInterface;
use App\Services\Ai\Chunker;
use App\Services\Ai\HybridSearchService;
use Database\Seeders\AgentFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use RuntimeException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function ragOpdUser(): User
{
    return testUser(UserRole::Verifier->value, testOpd());
}

function ragReadyDocument(?int $opdId = null): Document
{
    $document = Document::factory()
        ->for($opdId !== null ? Opd::query()->findOrFail($opdId) : testOpd(), 'opd')
        ->for(DocumentType::query()->where('code', 'spm')->firstOrFail(), 'documentType')
        ->create([
            'document_number' => 'SPM-777/2026',
            'fiscal_year' => 2026,
            'status' => DocumentStatus::Approved,
        ]);

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

    $document->pages()->create([
        'page_number' => 1,
        'extracted_text' => 'Nomor: SPM-777/2026 Tanggal: 15/03/2026 Jumlah Rp125.000.000 Belanja bahan habis pakai',
        'processing_status' => 'completed',
        'text_source' => 'embedded',
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

it('chunks whitelisted content and stores embeddings with metadata', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = ragReadyDocument();

    (new GenerateEmbeddingsJob($document->id, 1))->handle(
        app(AIProviderInterface::class),
        app(Chunker::class),
    );

    $chunks = $document->chunks()->get();

    expect($chunks->count())->toBeGreaterThan(0)
        ->and($chunks->first()->embedding)->not->toBeNull()
        ->and($chunks->first()->opd_id)->toBe($document->opd_id)
        ->and($chunks->first()->fiscal_year)->toBe(2026)
        ->and($chunks->first()->document_type)->toBe('spm')
        ->and($chunks->first()->version)->toBe(1)
        ->and($document->processingRuns()->where('stage', 'embedding')->sole()->status)->toBe('completed');
});

it('never chunks documents without a completed extraction', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = Document::factory()
        ->for(testOpd(), 'opd')
        ->create(['status' => DocumentStatus::Uploaded]);

    $document->processingRuns()->create([
        'version' => 1,
        'stage' => 'validation',
        'status' => 'completed',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    (new GenerateEmbeddingsJob($document->id, 1))->handle(
        app(AIProviderInterface::class),
        app(Chunker::class),
    );

    expect($document->chunks()->count())->toBe(0)
        ->and($document->processingRuns()->where('stage', 'embedding')->sole()->status)->toBe('completed');
});

it('replaces the chunks of the same version on re-run', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = ragReadyDocument();

    $job = new GenerateEmbeddingsJob($document->id, 1);
    $job->handle(app(AIProviderInterface::class), app(Chunker::class));

    $first = $document->chunks()->pluck('content')->all();

    $job->handle(app(AIProviderInterface::class), app(Chunker::class));

    // Idempotent: same count and same content, freshly stored.
    expect($document->chunks()->count())->toBe(count($first))
        ->and($document->chunks()->pluck('content')->all())->toBe($first);
});

it('records an advisory failure without touching the document state', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = ragReadyDocument();

    $ai = Mockery::mock(AIProviderInterface::class);
    $ai->shouldReceive('generateEmbedding')->andThrow(new RuntimeException('embedding provider down'));

    (new GenerateEmbeddingsJob($document->id, 1))->handle($ai, app(Chunker::class));

    $run = $document->processingRuns()->where('stage', 'embedding')->sole();

    expect($run->status)->toBe('failed')
        ->and($run->error)->toBe('embedding provider down')
        ->and($document->refresh()->status)->toBe(DocumentStatus::Approved)
        ->and($document->chunks()->count())->toBe(0);
});

it('runs embeddings after SIHANDAL matching in the pipeline', function () {
    fakeDocumentsDisk();
    $this->seed(AgentFinanceSeeder::class);

    actingAs(ragOpdUser());

    post(route('documents.store'), [
        'file' => UploadedFile::fake()->createWithContent(
            'spm.pdf',
            minimalPdf('Nomor: SPM-777/2026 Tanggal: 15/03/2026 Tahun Anggaran 2026 Jumlah Rp125.000.000 Belanja bahan habis pakai'),
        ),
        'fiscal_year' => 2026,
    ])->assertRedirect();

    $document = Document::query()->sole();

    expect($document->refresh()->status)->toBe(DocumentStatus::Approved)
        ->and($document->chunks()->count())->toBeGreaterThan(0)
        ->and($document->chunks()->first()->embedding)->not->toBeNull()
        ->and($document->processingRuns()->where('stage', 'sihandal_matching')->exists())->toBeTrue();
});

it('scopes hybrid search to the user OPD', function () {
    $this->seed(AgentFinanceSeeder::class);

    $user = ragOpdUser();
    $opd = $user->opd;

    $otherOpd = Opd::query()->create(['code' => '8.8.0', 'name' => 'Other OPD']);

    $own = ragReadyDocument($opd->id);
    $other = ragReadyDocument($otherOpd->id);

    foreach ([$own, $other] as $document) {
        $document->chunks()->create([
            'version' => 1,
            'chunk_index' => 0,
            'content' => 'Belanja bahan habis pakai untuk kegiatan sekolah',
            'embedding' => app(AIProviderInterface::class)
                ->generateEmbedding('Belanja bahan habis pakai', 'fake')->vector,
            'opd_id' => $document->opd_id,
            'fiscal_year' => 2026,
            'document_type' => 'spm',
        ]);
    }

    $hits = app(HybridSearchService::class)
        ->search('bahan habis pakai', $user);

    expect($hits->count())->toBe(1)
        ->and($hits->first()['document_id'])->toBe($own->id)
        ->and($hits->first()['score'])->toBeGreaterThan(0);
});

it('filters hybrid search by fiscal year and document type', function () {
    $this->seed(AgentFinanceSeeder::class);

    $user = ragOpdUser();
    $document = ragReadyDocument();

    foreach ([['2026', 'spm'], ['2025', 'sp2d']] as [$year, $type]) {
        $document->chunks()->create([
            'version' => 1,
            'chunk_index' => (int) $year,
            'content' => "Belanja bahan habis pakai tahun {$year}",
            'embedding' => app(AIProviderInterface::class)
                ->generateEmbedding("bahan habis pakai {$year}", 'fake')->vector,
            'opd_id' => $document->opd_id,
            'fiscal_year' => (int) $year,
            'document_type' => $type,
        ]);
    }

    $search = app(HybridSearchService::class);

    $byYear = $search->search('bahan habis pakai', $user, 2026);
    $byType = $search->search('bahan habis pakai', $user, null, 'sp2d');

    expect($byYear->count())->toBe(1)
        ->and($byYear->first()['fiscal_year'])->toBe(2026)
        ->and($byType->count())->toBe(1)
        ->and($byType->first()['document_type'])->toBe('sp2d');
});

it('degrades to lexical search when embeddings fail', function () {
    $this->seed(AgentFinanceSeeder::class);

    $user = ragOpdUser();
    $document = ragReadyDocument();

    $document->chunks()->create([
        'version' => 1,
        'chunk_index' => 0,
        'content' => 'Belanja bahan habis pakai untuk kegiatan sekolah',
        'embedding' => null,
        'opd_id' => $document->opd_id,
        'fiscal_year' => 2026,
        'document_type' => 'spm',
    ]);

    $ai = Mockery::mock(AIProviderInterface::class);
    $ai->shouldReceive('generateEmbedding')->andThrow(new RuntimeException('down'));

    $hits = app(HybridSearchService::class)
        ->search('bahan habis pakai', $user);

    // Swap in the failing provider: lexical-only still finds it.
    app()->instance(AIProviderInterface::class, $ai);

    $lexical = app(HybridSearchService::class)
        ->search('bahan habis pakai', $user);

    expect($hits->count())->toBe(1)
        ->and($lexical->count())->toBe(1)
        ->and($lexical->first()['score'])->toBeGreaterThan(0);
});
