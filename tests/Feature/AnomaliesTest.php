<?php

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyStatus;
use App\Enums\AnomalyType;
use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Jobs\GenerateEmbeddingsJob;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentAnomaly;
use App\Models\DocumentType;
use App\Models\Opd;
use App\Models\User;
use App\Services\Ai\AIProviderInterface;
use App\Services\Ai\Chunker;
use App\Services\Ai\KnowledgeContent;
use App\Services\Validation\Rules\SemanticDuplicateRule;
use App\Services\Validation\ValidationContext;
use App\Services\Validation\ValidationEngine;
use Database\Seeders\AgentFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function anomalyUser(): User
{
    return testUser(UserRole::Verifier->value, testOpd());
}

/**
 * A document with a completed extraction whose fields are
 * provided as [field => [value, confidence]], mirroring
 * what ExtractFinancialDataJob persists.
 */
function anomalyDocument(array $fields, ?string $typeCode = 'spm', array $attributes = []): Document
{
    $document = Document::factory()
        ->for(testOpd(), 'opd')
        ->for(DocumentType::query()->where('code', $typeCode)->firstOrFail(), 'documentType')
        ->create(array_merge([
            'document_number' => '800/123/SPM/2026',
            'fiscal_year' => 2026,
            'original_filename' => 'spm.pdf',
            'status' => DocumentStatus::ExtractionComplete,
        ], $attributes));

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

    $document->processingRuns()->create([
        'version' => 1,
        'stage' => 'validation',
        'status' => 'completed',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    return $document;
}

/**
 * The default field set used by the duplicate tests:
 * distinct number, shared payee/amount/date.
 *
 * @return array<string, array{0: string, 1: int}>
 */
function duplicateFields(string $number = '800/123/SPM/2026'): array
{
    return [
        'document_number' => [$number, 92],
        'document_date' => ['2026-03-15', 90],
        'fiscal_year' => ['2026', 95],
        'amount' => ['125000000', 91],
        'payee_name' => ['CV Mitra Jaya', 88],
    ];
}

it('detects the same payee, amount and date under different numbers', function () {
    $this->seed(AgentFinanceSeeder::class);

    $opd = testOpd();

    // Pre-existing document: same obligation, different number
    // (both at the table level and in the extraction fields).
    anomalyDocument(
        duplicateFields('800/999/SPM/2026'),
        'spm',
        ['document_number' => '800/999/SPM/2026'],
    );

    $document = anomalyDocument(duplicateFields('800/123/SPM/2026'));
    $document->update(['opd_id' => $opd->id]);

    app(ValidationEngine::class)->validate($document);

    $anomaly = $document->anomalies()->where('type', 'duplicate')->sole();

    $matchedOn = collect($anomaly->evidence['findings'])
        ->pluck('matched_on')
        ->all();

    expect($anomaly->severity->value)->toBe('high')
        ->and($matchedOn)->toContain('payee_amount_date')
        ->and($anomaly->evidence['findings'][0]['duplicate_document_id'] ?? null)->toBeGreaterThan(0)
        ->and($document->refresh()->status)->toBe(DocumentStatus::ReviewRequired);
});

it('detects near-identical content via semantic similarity', function () {
    $this->seed(AgentFinanceSeeder::class);

    $opd = testOpd();
    $type = DocumentType::query()->where('code', 'spm')->firstOrFail();

    // First document: already in the knowledge base.
    $first = anomalyDocument(duplicateFields());
    $first->update(['opd_id' => $opd->id]);

    (new GenerateEmbeddingsJob($first->id, 1))->handle(
        app(AIProviderInterface::class),
        app(Chunker::class),
        app(KnowledgeContent::class),
    );

    expect($first->chunks()->count())->toBeGreaterThan(0);

    // Re-scan of the same obligation under the same number
    // and filename: identical whitelisted content.
    $second = anomalyDocument(duplicateFields());
    $second->update(['opd_id' => $opd->id]);

    app(ValidationEngine::class)->validate($second);

    $anomaly = $second->anomalies()->where('type', 'duplicate')->sole();

    $matchedOn = collect($anomaly->evidence['findings'])
        ->pluck('matched_on')
        ->all();

    expect($matchedOn)->toContain('content_embedding')
        ->and($anomaly->evidence['findings'])->toBeArray();
});

it('ignores unrelated content in the semantic scan', function () {
    $this->seed(AgentFinanceSeeder::class);

    $opd = testOpd();

    $first = anomalyDocument(duplicateFields());
    $first->update(['opd_id' => $opd->id]);

    (new GenerateEmbeddingsJob($first->id, 1))->handle(
        app(AIProviderInterface::class),
        app(Chunker::class),
        app(KnowledgeContent::class),
    );

    // Different obligation: different payee, amount and date.
    $second = anomalyDocument([
        'document_number' => ['800/456/SPM/2026', 92],
        'document_date' => ['2026-04-01', 90],
        'fiscal_year' => ['2026', 95],
        'amount' => ['9500000', 91],
        'payee_name' => ['PT Harapan Kita', 88],
    ]);
    $second->update(['opd_id' => $opd->id]);

    app(ValidationEngine::class)->validate($second);

    expect($second->anomalies()->where('type', 'duplicate')->count())->toBe(0);
});

it('scopes semantic duplicates to the OPD', function () {
    $this->seed(AgentFinanceSeeder::class);

    $opd = testOpd();
    $otherOpd = Opd::query()->create(['code' => '8.8.0', 'name' => 'Other OPD']);

    // Identical content, but in another OPD.
    $first = anomalyDocument(duplicateFields(), 'spm', ['opd_id' => $otherOpd->id]);

    (new GenerateEmbeddingsJob($first->id, 1))->handle(
        app(AIProviderInterface::class),
        app(Chunker::class),
        app(KnowledgeContent::class),
    );

    $second = anomalyDocument(duplicateFields());
    $second->update(['opd_id' => $opd->id]);

    app(ValidationEngine::class)->validate($second);

    // No duplicate indicator: the semantic rule never
    // crosses the OPD boundary (§36).
    $matchedOn = collect($second->anomalies()->where('type', 'duplicate')->get())
        ->flatMap(fn (DocumentAnomaly $a) => collect($a->evidence['findings'] ?? [])->pluck('matched_on'))
        ->all();

    expect($matchedOn)->not->toContain('content_embedding');
});

it('matches semantic duplicates in isolation with the rule directly', function () {
    $this->seed(AgentFinanceSeeder::class);

    $opd = testOpd();
    $type = DocumentType::query()->where('code', 'spm')->firstOrFail();

    $fields = duplicateFields();

    $document = anomalyDocument($fields);
    $extraction = $document->extractions()->sole();

    $context = new ValidationContext(
        document: $document,
        extraction: $extraction,
        fields: new Collection($extraction->fields()->get()->keyBy('field')->all()),
        highConfidence: 85,
        lowConfidence: 60,
    );

    // A chunk of identical content from another document
    // in the same OPD.
    $other = Document::factory()
        ->for($opd, 'opd')
        ->for($type, 'documentType')
        ->create([
            'document_number' => $document->document_number,
            'fiscal_year' => 2026,
            'original_filename' => $document->original_filename,
        ]);

    $content = app(KnowledgeContent::class)->content($other, $extraction->payload);
    $vector = app(AIProviderInterface::class)
        ->generateEmbedding($content, 'fake-model')->vector;

    $other->chunks()->create([
        'version' => 1,
        'chunk_index' => 0,
        'content' => $content,
        'embedding' => $vector,
        'opd_id' => $opd->id,
        'fiscal_year' => 2026,
        'document_type' => 'spm',
    ]);

    $rule = new SemanticDuplicateRule(
        app(AIProviderInterface::class),
        app(KnowledgeContent::class),
    );

    $findings = $rule->check($context);

    expect($findings)->not->toBeEmpty()
        ->and($findings[0]->type->value)->toBe('duplicate')
        ->and($findings[0]->severity->value)->toBe('high')
        ->and($findings[0]->evidence['matched_on'])->toBe('content_embedding')
        ->and($findings[0]->evidence['similarity'])->toBeGreaterThan(0.95);
});

it('resolves an anomaly recording the human decision', function () {
    $this->seed(AgentFinanceSeeder::class);

    $user = anomalyUser();

    $document = anomalyDocument(duplicateFields());
    $anomaly = $document->anomalies()->create([
        'type' => AnomalyType::Duplicate,
        'severity' => AnomalySeverity::High,
        'status' => AnomalyStatus::Open,
        'title' => 'Possible duplicate',
        'detail' => 'Same payee, amount and date.',
    ]);

    actingAs($user);

    post(route('anomalies.resolve', $anomaly), [], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('status', 'resolved');

    $anomaly->refresh();

    expect($anomaly->status->value)->toBe('resolved')
        ->and($anomaly->resolved_by)->toBe($user->id)
        ->and($anomaly->resolved_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'anomaly.resolved')->where('subject_id', $anomaly->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'anomaly.resolved')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('dismisses an anomaly as not an issue', function () {
    $this->seed(AgentFinanceSeeder::class);

    $user = anomalyUser();

    $document = anomalyDocument(duplicateFields());
    $anomaly = $document->anomalies()->create([
        'type' => AnomalyType::LowConfidence,
        'severity' => AnomalySeverity::Warning,
        'status' => AnomalyStatus::Open,
        'title' => 'Low confidence extraction',
    ]);

    actingAs($user);

    post(route('anomalies.dismiss', $anomaly), [], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('status', 'dismissed');

    expect($anomaly->refresh()->status->value)->toBe('dismissed')
        ->and($anomaly->resolved_by)->toBe($user->id);
});

it('denies anomaly decisions to viewers and other OPDs', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = anomalyDocument(duplicateFields());
    $anomaly = $document->anomalies()->create([
        'type' => AnomalyType::Duplicate,
        'severity' => AnomalySeverity::High,
        'status' => AnomalyStatus::Open,
        'title' => 'Possible duplicate',
    ]);

    // Viewers can look but never decide (§36).
    $viewer = testUser(UserRole::Viewer->value, testOpd());

    actingAs($viewer);

    post(route('anomalies.resolve', $anomaly), [], ['Accept' => 'application/json'])
        ->assertForbidden();

    // A verifier from another OPD cannot decide either.
    $otherOpd = Opd::query()->create(['code' => '8.8.0', 'name' => 'Other OPD']);
    $otherVerifier = testUser(UserRole::Verifier->value, $otherOpd);

    actingAs($otherVerifier);

    post(route('anomalies.resolve', $anomaly), [], ['Accept' => 'application/json'])
        ->assertForbidden();

    expect($anomaly->refresh()->status->value)->toBe('open');
});

it('lists open anomalies scoped to the user OPD', function () {
    $this->seed(AgentFinanceSeeder::class);

    $user = anomalyUser();

    $own = anomalyDocument(duplicateFields());
    $own->anomalies()->create([
        'type' => AnomalyType::Duplicate,
        'severity' => AnomalySeverity::Critical,
        'status' => AnomalyStatus::Open,
        'title' => 'Possible duplicate: same document number',
    ]);

    // Another OPD's anomaly never appears.
    $otherOpd = Opd::query()->create(['code' => '8.8.0', 'name' => 'Other OPD']);
    $otherDocument = anomalyDocument(duplicateFields('800/555/SPM/2026'), 'spm', ['opd_id' => $otherOpd->id]);
    $otherDocument->anomalies()->create([
        'type' => AnomalyType::TotalMismatch,
        'severity' => AnomalySeverity::High,
        'status' => AnomalyStatus::Open,
        'title' => 'Secret anomaly from another OPD',
    ]);

    actingAs($user);

    get('/anomalies')
        ->assertOk()
        ->assertSeeText('Possible duplicate: same document number')
        ->assertDontSeeText('Secret anomaly from another OPD');
});

it('filters anomalies by severity', function () {
    $this->seed(AgentFinanceSeeder::class);

    $user = anomalyUser();

    $document = anomalyDocument(duplicateFields());
    $document->anomalies()->create([
        'type' => AnomalyType::Duplicate,
        'severity' => AnomalySeverity::Critical,
        'status' => AnomalyStatus::Open,
        'title' => 'Critical duplicate',
    ]);
    $document->anomalies()->create([
        'type' => AnomalyType::LowConfidence,
        'severity' => AnomalySeverity::Info,
        'status' => AnomalyStatus::Open,
        'title' => 'Info confidence',
    ]);

    actingAs($user);

    get('/anomalies?severity=critical')
        ->assertOk()
        ->assertSeeText('Critical duplicate')
        ->assertDontSeeText('Info confidence');
});
