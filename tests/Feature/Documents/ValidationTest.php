<?php

use App\Enums\DocumentStatus;
use App\Jobs\ExtractFinancialDataJob;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentExtractionField;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\Ai\AIProviderInterface;
use App\Services\Ai\PromptRegistry;
use App\Services\Extraction\ExtractionSchemaRegistry;
use App\Services\Validation\ValidationEngine;
use Database\Seeders\AgentFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function validationUser(): User
{
    return testUser('verifier', testOpd());
}

/**
 * A document with a completed SPM extraction whose fields are provided as
 * [field => [value, confidence]]. Mirrors what ExtractFinancialDataJob
 * persists, without re-running the AI layer.
 */
function documentWithExtraction(array $fields, ?string $typeCode = 'spm'): Document
{
    $document = Document::factory()
        ->for(DocumentType::query()->where('code', $typeCode)->firstOrFail(), 'documentType')
        ->create();

    $extraction = $document->extractions()->create([
        'version' => 1,
        'schema_name' => $typeCode,
        'schema_version' => '1.0',
        'payload' => collect($fields)->map(fn (array $f) => $f[0])->all(),
        'status' => 'completed',
        'confidence' => 90,
    ]);

    foreach ($fields as $name => [$value, $confidence]) {
        $extraction->fields()->create([
            'field' => $name,
            'value' => $value,
            'confidence' => $confidence,
        ]);
    }

    return $document;
}

it('auto-approves a clean confident extraction', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = documentWithExtraction([
        'document_number' => ['800/123/SPM/2026', 92],
        'document_date' => ['2026-03-15', 90],
        'fiscal_year' => ['2026', 95],
        'amount' => ['125000000', 91],
    ]);

    // Uploader-assigned fiscal year matches the extraction: no mismatch.
    $document->update(['fiscal_year' => 2026, 'document_number' => '800/123/SPM/2026']);

    app(ValidationEngine::class)->validate($document);

    expect($document->refresh()->status)->toBe(DocumentStatus::Approved)
        ->and($document->validations()->sole()->result)->toBe('passed')
        ->and($document->anomalies()->count())->toBe(0)
        ->and($document->reviews()->where('decision', 'approved')->count())->toBe(1);
});

it('routes to review_required on missing document number', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = documentWithExtraction([
        'document_number' => [null, 10],
        'document_date' => ['2026-03-15', 90],
        'fiscal_year' => ['2026', 95],
        'amount' => ['125000000', 91],
    ]);

    app(ValidationEngine::class)->validate($document);

    $missing = $document->anomalies()->where('type', 'missing_document_number')->sole();

    expect($document->refresh()->status)->toBe(DocumentStatus::ReviewRequired)
        ->and($missing->severity->value)->toBe('high')
        ->and($missing->status->value)->toBe('open')
        ->and($document->validations()->sole()->result)->toBe('failed');
});

it('flags fiscal year mismatch as a warning', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = documentWithExtraction([
        'document_number' => ['800/123/SPM/2026', 92],
        'fiscal_year' => ['2025', 90], // extracted year differs from assigned
        'amount' => ['125000000', 91],
    ]);

    $document->update(['fiscal_year' => 2026]);

    app(ValidationEngine::class)->validate($document);

    $anomaly = $document->anomalies()->where('type', 'fiscal_year_mismatch')->sole();

    // A warning-severity mismatch still routes to review (any open anomaly
    // blocks auto-approval), with the mismatch detail on the anomaly.
    expect($anomaly->severity->value)->toBe('warning')
        ->and($anomaly->evidence['findings'][0]['expected'])->toBe(2026)
        ->and($anomaly->evidence['findings'][0]['actual'])->toBe(2025)
        ->and($document->refresh()->status)->toBe(DocumentStatus::ReviewRequired);
});

it('routes a low-confidence field to review without flagging absent optional fields', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = documentWithExtraction([
        'document_number' => ['800/123/SPM/2026', 92],
        'fiscal_year' => ['2026', 95],
        'amount' => ['125000000', 91],
        'payee_name' => ['CV Mitra Jaya', 40], // suspect value
        // opd_name, amount_in_words etc. simply absent (optional): no finding.
    ]);

    $document->update(['fiscal_year' => 2026]);

    app(ValidationEngine::class)->validate($document);

    // type is enum-cast: compare on the backing value.
    $types = $document->anomalies()->pluck('type')->map(fn ($type) => $type->value);

    expect($types)->toContain('low_confidence')
        ->and($types)->not->toContain('missing_document_number')
        ->and($document->refresh()->status)->toBe(DocumentStatus::ReviewRequired);
});

it('detects a duplicate document number within the same OPD', function () {
    $this->seed(AgentFinanceSeeder::class);

    $opd = testOpd();

    // Pre-existing document with the same number in the same OPD.
    Document::factory()->for($opd, 'opd')->create([
        'document_number' => '800/123/SPM/2026',
    ]);

    $document = documentWithExtraction([
        'document_number' => ['800/123/SPM/2026', 92],
        'fiscal_year' => ['2026', 95],
        'amount' => ['125000000', 91],
    ]);
    $document->update(['opd_id' => $opd->id, 'fiscal_year' => 2026]);

    app(ValidationEngine::class)->validate($document);

    $duplicate = $document->anomalies()->where('type', 'duplicate')->sole();

    expect($duplicate->severity->value)->toBe('critical')
        ->and($duplicate->evidence['findings'][0]['duplicate_document_id'])->toBeGreaterThan(0)
        ->and($document->refresh()->status)->toBe(DocumentStatus::ReviewRequired);
});

it('detects line items that do not sum to the total', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = documentWithExtraction([
        'document_number' => ['800/123/SPM/2026', 92],
        'fiscal_year' => ['2026', 95],
        'amount' => ['1000000', 91],
    ]);

    $document->update(['fiscal_year' => 2026]);

    // Items sum to 1.5M but the stated total is 1.0M (from the field above).
    $extraction = $document->extractions()->first();
    $extraction->update(['payload' => [
        'document_number' => '800/123/SPM/2026',
        'fiscal_year' => 2026,
        'amount' => 1000000,
        'items' => [
            ['description' => 'A', 'amount' => 750000],
            ['description' => 'B', 'amount' => 750000],
        ],
    ]]);

    app(ValidationEngine::class)->validate($document);

    $anomaly = $document->anomalies()->where('type', 'total_mismatch')->sole();

    expect($anomaly->severity->value)->toBe('high')
        ->and($document->refresh()->status)->toBe(DocumentStatus::ReviewRequired);
});

it('records an audit entry when validation routes a document', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = documentWithExtraction([
        'document_number' => ['800/123/SPM/2026', 92],
        'fiscal_year' => ['2026', 95],
        'amount' => ['125000000', 91],
    ]);
    $document->update(['fiscal_year' => 2026]);

    app(ValidationEngine::class)->validate($document);

    expect(AuditLog::query()->where('action', 'document.validated')->where('subject_id', $document->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'document.auto_approved')->where('subject_id', $document->id)->exists())->toBeTrue();
});

it('runs validation in the sync pipeline after extraction', function () {
    fakeDocumentsDisk();
    $this->seed(AgentFinanceSeeder::class);

    actingAs(validationUser());

    $document = Document::factory()->create();
    $document->processingRuns()->create(['version' => 1, 'stage' => 'pipeline', 'status' => 'pending']);
    $document->pages()->create([
        'page_number' => 1,
        'extracted_text' => 'SURAT PERINTAH MEMBAYAR Nomor: SPM-777/2026 Jumlah Rp125.000.000',
        'text_source' => 'embedded',
        'processing_status' => 'done',
    ]);

    (new ExtractFinancialDataJob($document->id, 1))->handle(
        app(AIProviderInterface::class),
        app(ExtractionSchemaRegistry::class),
        app(PromptRegistry::class),
    );

    // Extraction dispatches validation (QUEUE_CONNECTION=sync): the pipeline
    // continues past extraction_complete (spec §5).
    $document->refresh();

    expect($document->extractions()->count())->toBe(1)
        ->and($document->validations()->count())->toBe(1)
        ->and($document->status)->toBeIn([DocumentStatus::Approved, DocumentStatus::ReviewRequired]);
});

it('is idempotent on re-validation: replaces the open anomaly set', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = documentWithExtraction([
        'document_number' => [null, 10], // missing → anomaly
        'fiscal_year' => ['2026', 95],
        'amount' => ['125000000', 91],
    ]);

    app(ValidationEngine::class)->validate($document);

    // Missing required field + the extraction's absent optional fields at low
    // confidence collapse into per-type anomalies: missing_document_number +
    // low_confidence.
    expect($document->anomalies()->whereIn('type', ['missing_document_number', 'low_confidence'])->count())->toBe(2)
        ->and($document->anomalies()->where('type', 'missing_document_number')->count())->toBe(1);

    // Fix the value (as a human correction would), re-validate.
    $document->extractions()->first()->fields()
        ->where('field', 'document_number')->first()
        ->update(['value' => '800/123/SPM/2026', 'confidence' => 95]);
    $document->update(['document_number' => '800/123/SPM/2026']);

    app(ValidationEngine::class)->validate($document);

    expect($document->anomalies()->count())->toBe(0)
        ->and($document->validations()->count())->toBe(2)
        ->and($document->validations()->orderByDesc('version')->first()->result)->toBe('passed');
});

it('keeps a DocumentExtractionField factory-free path', function () {
    // Guard against regressions: extraction fields persist with numeric
    // confidence for the validation context to consume.
    $this->seed(AgentFinanceSeeder::class);

    $document = documentWithExtraction([
        'document_number' => ['X/2026', 55],
    ]);

    $field = DocumentExtractionField::query()
        ->where('document_extraction_id', $document->extractions()->sole()->id)
        ->where('field', 'document_number')
        ->sole();

    expect($field->value)->toBe('X/2026')
        ->and($field->confidence)->toBe(55);
});
