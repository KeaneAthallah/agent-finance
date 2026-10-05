<?php

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyStatus;
use App\Enums\AnomalyType;
use App\Enums\DocumentStatus;
use App\Enums\SihandalMatchStatus;
use App\Enums\SihandalSyncStatus;
use App\Enums\UserRole;
use App\Models\AiRun;
use App\Models\AiUsage;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Opd;
use App\Models\User;
use Database\Seeders\AgentFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function reportUser(): User
{
    return testUser(UserRole::Verifier->value, testOpd());
}

function reportDocument(array $attributes = []): Document
{
    return Document::factory()
        ->for(testOpd(), 'opd')
        ->for(DocumentType::query()->where('code', 'spm')->sole(), 'documentType')
        ->create(array_merge([
            'status' => DocumentStatus::ExtractionComplete,
            'fiscal_year' => 2026,
        ], $attributes));
}

it('computes document, confidence, workload, sync and cost reports', function () {
    $this->seed(AgentFinanceSeeder::class);

    $opd = testOpd();

    // Two processed documents, one awaiting review with a
    // critical anomaly, one approved.
    $review = reportDocument(['status' => DocumentStatus::ReviewRequired]);
    reportDocument(['status' => DocumentStatus::Approved]);

    $extraction = $review->extractions()->create([
        'version' => 1,
        'schema_name' => 'spm',
        'schema_version' => '1.0',
        'payload' => ['amount' => 125000000],
        'status' => 'completed',
        'confidence' => 80,
    ]);

    $extraction->fields()->createMany([
        ['field' => 'amount', 'value' => '125000000', 'confidence' => 90],
        ['field' => 'document_date', 'value' => '2026-03-15', 'confidence' => 70],
        ['field' => 'payee_name', 'value' => 'CV Mitra Jaya', 'confidence' => 40],
    ]);

    $review->anomalies()->create([
        'type' => AnomalyType::Duplicate,
        'severity' => AnomalySeverity::Critical,
        'status' => AnomalyStatus::Open,
        'title' => 'Possible duplicate',
    ]);

    // SIHANDAL state: one pending match, one pending sync,
    // one successful sync.
    $match = $review->matches()->create([
        'version' => 1,
        'entity_type' => 'belanja',
        'entity_id' => 'SPM-001',
        'sihandal_data' => ['uraian' => 'SPM-001'],
        'confidence' => 90,
        'status' => SihandalMatchStatus::Pending,
    ]);

    $review->syncs()->create([
        'sihandal_match_id' => $match->id,
        'endpoint' => '/v1/belanja',
        'request' => ['entity_id' => 'SPM-001'],
        'status' => SihandalSyncStatus::Pending,
    ]);

    reportDocument(['status' => DocumentStatus::Synced])
        ->syncs()->create([
            'endpoint' => '/v1/belanja',
            'request' => [],
            'status' => SihandalSyncStatus::Success,
        ]);

    // AI usage: two calls on the review document's runs.
    foreach (['extraction', 'embedding'] as $stage) {
        $run = AiRun::query()->create([
            'document_id' => $review->id,
            'stage' => $stage,
            'provider' => 'fake',
            'model' => 'fake-model',
            'status' => 'completed',
            'input_tokens' => 1000,
            'output_tokens' => 200,
            'duration_ms' => 1500,
        ]);

        AiUsage::query()->create([
            'ai_run_id' => $run->id,
            'provider' => 'fake',
            'model' => 'fake-model',
            'input_tokens' => 1000,
            'output_tokens' => 200,
            'cost_estimate' => '0.00250000',
            'duration_ms' => 1500,
        ]);
    }

    actingAs(testUser(UserRole::SuperAdmin->value, null));

    get('/reports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->where('scope', 'platform')
            ->where('documents.total', 3)
            ->where('documents.by_status.review_required', 1)
            ->where('documents.by_status.approved', 1)
            ->where('documents.by_fiscal_year.2026', 3)
            ->where('documents.by_type.spm', 3)
            ->where('confidence.high', 1)
            ->where('confidence.medium', 1)
            ->where('confidence.low', 1)
            ->where('workload.review_required', 1)
            ->where('workload.open_anomalies', 1)
            ->where('workload.critical_anomalies', 1)
            ->where('workload.pending_sihandal_confirmations', 1)
            ->where('sync_health.pending', 1)
            ->where('sync_health.success', 1)
            ->where('sync_health.failed', 0)
            ->where('ai_cost.calls', 2)
            ->where('ai_cost.input_tokens', 2000)
            ->where('ai_cost.output_tokens', 400)
            ->where('ai_cost.cost_estimate', '0.00500000')
            ->where('ai_cost_by_model', [
                [
                    'model' => 'fake-model',
                    'calls' => 2,
                    'input_tokens' => 2000,
                    'output_tokens' => 400,
                    'cost_estimate' => '0.00500000',
                ],
            ]));
});

it('scopes report metrics to the user OPD (§36)', function () {
    $this->seed(AgentFinanceSeeder::class);

    $opd = testOpd();
    $otherOpd = Opd::query()->create(['code' => '8.8.0', 'name' => 'Other OPD']);

    // Own OPD: one approved document.
    reportDocument(['status' => DocumentStatus::Approved]);

    // Another OPD: one review document — must stay invisible.
    Document::factory()
        ->for($otherOpd, 'opd')
        ->create(['status' => DocumentStatus::ReviewRequired, 'fiscal_year' => 2026]);

    actingAs(reportUser());

    get('/reports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->where('scope', 'opd')
            ->where('documents.total', 1)
            ->where('documents.by_status.approved', 1)
            ->where('workload.review_required', 0));
});
