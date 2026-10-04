<?php

use App\Actions\SyncToSihandalAction;
use App\Enums\DocumentStatus;
use App\Enums\SihandalMatchStatus;
use App\Enums\SihandalSyncStatus;
use App\Enums\UserRole;
use App\Jobs\MatchSihandalDataJob;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Opd;
use App\Models\SihandalMatch;
use App\Models\User;
use App\Services\Sihandal\MatchingService;
use App\Services\Sihandal\SihandalApiInterface;
use Database\Seeders\AgentFinanceSeeder;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function sihandalOpd(): Opd
{
    return Opd::query()->where('code', '4.01.0')->firstOrFail();
}

function sihandalUser(): User
{
    return testUser(UserRole::Verifier->value, sihandalOpd());
}

/**
 * An approved SPM document whose extracted facts exactly match the
 * fake SIHANDAL belanja fixture (SPM-777/2026, Rp125.000.000,
 * fiscal 2026, OPD 4.01.0), with the pipeline run rows the matching
 * job expects after validation.
 */
function sihandalReadyDocument(): Document
{
    $document = Document::factory()
        ->for(sihandalOpd(), 'opd')
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

    $document->processingRuns()->create([
        'version' => 1,
        'stage' => 'validation',
        'status' => 'completed',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    return $document;
}

function pendingMatch(Document $document, int $confidence = 100): SihandalMatch
{
    return $document->matches()->create([
        'version' => 1,
        'entity_type' => 'belanja',
        'entity_id' => '1001',
        'sihandal_data' => [
            'id' => 1001,
            'rekening' => '5.1.02.01.0001',
            'uraian' => 'Belanja Bahan Habis Pakai',
            'amount' => 125000000,
            'fiscal_year' => 2026,
            'document_number' => 'SPM-777/2026',
        ],
        'confidence' => $confidence,
        'status' => SihandalMatchStatus::Pending->value,
    ]);
}

/** A SIHANDAL client whose sync writes throw the given exception. */
function sihandalSyncFailureClient(Throwable $exception): SihandalApiInterface
{
    $client = Mockery::mock(SihandalApiInterface::class);
    $client->shouldReceive('syncDocument')->andThrow($exception);

    return $client;
}

it('matches extraction data against SIHANDAL, confidence-ordered', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = sihandalReadyDocument();

    $matches = app(MatchingService::class)->match($document, app(SihandalApiInterface::class));

    // Exact number + amount + fiscal year + OPD = 100 on item 1001;
    // amount + fiscal year only = 40 on item 1002.
    expect($matches->count())->toBe(2)
        ->and($matches[0]->confidence)->toBe(100)
        ->and($matches[0]->entity_id)->toBe('1001')
        ->and($matches[0]->status)->toBe(SihandalMatchStatus::Pending)
        ->and($matches[1]->confidence)->toBe(40)
        ->and($matches[1]->entity_id)->toBe('1002');
});

it('replaces prior pending candidates for the same version', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = sihandalReadyDocument();

    $service = app(MatchingService::class);
    $client = app(SihandalApiInterface::class);

    $first = $service->match($document, $client);
    $second = $service->match($document, $client);

    expect($first->count())->toBe(2)
        ->and($second->count())->toBe(2)
        ->and($document->matches()->where('status', 'pending')->count())->toBe(2)
        ->and($document->matches()->whereIn('id', $first->modelKeys())->exists())->toBeFalse();
});

it('keeps confirmed and rejected candidates as history', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = sihandalReadyDocument();

    $match = pendingMatch($document);
    $match->update(['status' => SihandalMatchStatus::Confirmed->value]);

    $matches = app(MatchingService::class)->match($document, app(SihandalApiInterface::class));

    expect($matches->count())->toBe(2)
        ->and($document->matches()->count())->toBe(3)
        ->and($document->matches()->where('status', 'confirmed')->count())->toBe(1);
});

it('is failure-safe when SIHANDAL is unreachable (spec §50)', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = sihandalReadyDocument();

    $sihandal = Mockery::mock(SihandalApiInterface::class);
    $sihandal->shouldReceive('belanja')->andThrow(new ConnectionException('unreachable'));

    (new MatchSihandalDataJob($document->id, 1))->handle(
        app(MatchingService::class),
        $sihandal,
    );

    $run = $document->processingRuns()->where('stage', 'sihandal_matching')->sole();

    expect($document->refresh()->status)->toBe(DocumentStatus::Approved)
        ->and($run->status)->toBe('failed')
        ->and($run->error)->toBe('unreachable')
        ->and($document->matches()->count())->toBe(0);
});

it('runs matching after validation in the sync pipeline', function () {
    fakeDocumentsDisk();
    $this->seed(AgentFinanceSeeder::class);

    actingAs(sihandalUser());

    post(route('documents.store'), [
        'file' => UploadedFile::fake()->createWithContent(
            'spm.pdf',
            minimalPdf('Nomor: SPM-777/2026 Tanggal: 15/03/2026 Tahun Anggaran 2026 Jumlah Rp125.000.000'),
        ),
        'fiscal_year' => 2026,
    ])->assertRedirect();

    $document = Document::query()->sole();

    expect($document->refresh()->status)->toBe(DocumentStatus::Approved);

    $matches = $document->matches()->orderByDesc('confidence')->get();

    expect($matches->count())->toBe(2)
        ->and($matches[0]->confidence)->toBe(100)
        ->and($matches[0]->entity_id)->toBe('1001');
});

it('syncs an approved document to SIHANDAL on confirm', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = sihandalReadyDocument();
    $match = pendingMatch($document);
    $user = sihandalUser();

    actingAs($user);

    post(route('documents.sihandal.match.confirm', [$document, $match]))
        ->assertRedirect();

    $match->refresh();
    $sync = $document->syncs()->sole();

    expect($match->status)->toBe(SihandalMatchStatus::Confirmed)
        ->and($match->confirmed_by)->toBe($user->id)
        ->and($document->refresh()->status)->toBe(DocumentStatus::Synced)
        ->and($document->synced_at)->not->toBeNull()
        ->and($sync->status)->toBe(SihandalSyncStatus::Success)
        ->and($sync->endpoint)->toBe('/api/v1/documents')
        ->and($sync->http_status)->toBe(201)
        ->and($sync->request['document_number'])->toBe('SPM-777/2026')
        ->and($sync->response['status'])->toBe('recorded')
        ->and(AuditLog::query()->where('action', 'document.synced')->where('actor_type', 'human')->exists())->toBeTrue();
});

it('records a rejected candidate with the deciding user', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = sihandalReadyDocument();
    $match = pendingMatch($document);
    $user = sihandalUser();

    actingAs($user);

    post(route('documents.sihandal.match.reject', [$document, $match]))
        ->assertRedirect();

    expect($match->refresh()->status)->toBe(SihandalMatchStatus::Rejected)
        ->and($match->confirmed_by)->toBe($user->id)
        ->and($document->refresh()->status)->toBe(DocumentStatus::Approved)
        ->and($document->syncs()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'sihandal.match_rejected')->where('actor_type', 'human')->exists())->toBeTrue();
});

it('keeps the sync pending and retryable when SIHANDAL is unreachable', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = sihandalReadyDocument();
    $match = pendingMatch($document);
    $user = sihandalUser();

    $action = new SyncToSihandalAction(
        sihandalSyncFailureClient(new ConnectionException('unreachable')),
    );

    $sync = $action->execute($document, $match, $user);

    expect($sync->status)->toBe(SihandalSyncStatus::Pending)
        ->and($sync->attempts)->toBe(2)
        ->and(str($sync->error)->contains('unreachable'))->toBeTrue()
        ->and($document->refresh()->status)->toBe(DocumentStatus::Approved)
        ->and($match->refresh()->status)->toBe(SihandalMatchStatus::Pending);
});

it('fails the document loudly on SIHANDAL API errors', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = sihandalReadyDocument();
    $match = pendingMatch($document);
    $user = sihandalUser();

    $action = new SyncToSihandalAction(
        sihandalSyncFailureClient(new RequestException(
            new Response(new Psr7Response(500, [], json_encode(['message' => 'rejected']))),
        )),
    );

    $sync = $action->execute($document, $match, $user);

    expect($sync->status)->toBe(SihandalSyncStatus::Failed)
        ->and($sync->http_status)->toBe(500)
        ->and($sync->response['message'])->toBe('rejected')
        ->and($document->refresh()->status)->toBe(DocumentStatus::SyncFailed)
        ->and(AuditLog::query()->where('action', 'document.sync_failed')->where('actor_type', 'system')->exists())->toBeTrue();
});

it('retries a pending sync with the same payload', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = sihandalReadyDocument();
    $match = pendingMatch($document);
    $user = sihandalUser();

    $action = new SyncToSihandalAction(
        sihandalSyncFailureClient(new ConnectionException('unreachable')),
    );

    $sync = $action->execute($document, $match, $user);

    // SIHANDAL comes back online; the default (fake) client now works.
    $retried = (new SyncToSihandalAction(
        app(SihandalApiInterface::class),
    ))->retry($sync, $user);

    expect($retried->status)->toBe(SihandalSyncStatus::Success)
        ->and($retried->attempts)->toBe(3)
        ->and($document->refresh()->status)->toBe(DocumentStatus::Synced)
        ->and($match->refresh()->status)->toBe(SihandalMatchStatus::Confirmed);
});

it('requires the review ability to confirm a candidate', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = sihandalReadyDocument();
    $match = pendingMatch($document);

    // Viewer: no review ability.
    $viewer = testUser(UserRole::Viewer->value, sihandalOpd());

    actingAs($viewer);

    post(route('documents.sihandal.match.confirm', [$document, $match]))
        ->assertForbidden();
});

it('enforces OPD isolation on sihandal decisions', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = sihandalReadyDocument();
    $match = pendingMatch($document);

    // Verifier from a different OPD.
    $other = testUser(UserRole::Verifier->value, testOpd());

    actingAs($other);

    post(route('documents.sihandal.match.confirm', [$document, $match]))
        ->assertForbidden();

    post(route('documents.sihandal.match.reject', [$document, $match]))
        ->assertForbidden();
});

it('only syncs approved documents', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = sihandalReadyDocument();
    $document->update(['status' => DocumentStatus::ReviewRequired]);

    $match = pendingMatch($document);

    actingAs(sihandalUser());

    post(route('documents.sihandal.match.confirm', [$document, $match]))
        ->assertRedirect()
        ->assertSessionHasErrors('sihandal');

    expect($document->refresh()->status)->toBe(DocumentStatus::ReviewRequired)
        ->and($match->refresh()->status)->toBe(SihandalMatchStatus::Pending);
});

it('renders the sihandal section with counts and candidates', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = sihandalReadyDocument();
    pendingMatch($document, 100);
    pendingMatch($document, 40);

    actingAs(sihandalUser());

    get(route('sihandal.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sihandal/index')
            ->where('counts.pending_matches', 2)
            ->has('pendingMatches.data', 2));
});

it('surfaces sihandal candidates and syncs on the document detail page', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = sihandalReadyDocument();
    $match = pendingMatch($document);

    actingAs(sihandalUser());

    get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('documents/show')
            ->has('sihandal.matches', 1)
            ->where('sihandal.matches.0.confidence', 100)
            ->has('sihandal.syncs', 0));
});

it('rejects unauthenticated api clients', function () {
    $this->seed(AgentFinanceSeeder::class);

    get('/api/v1/documents')->assertUnauthorized();
    post('/api/v1/documents')->assertUnauthorized();
});

it('lists documents for a sanctum machine client', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = sihandalReadyDocument();
    $user = sihandalUser();
    $token = $user->createToken('machine-client');

    get('/api/v1/documents', ['Authorization' => 'Bearer '.$token->plainTextToken])
        ->assertOk()
        ->assertJsonPath('data.0.id', $document->id);
});

it('returns match candidates over the api', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = sihandalReadyDocument();
    pendingMatch($document, 100);

    $token = sihandalUser()->createToken('machine-client');

    get("/api/v1/documents/{$document->id}/sihandal/matches", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('matches.0.confidence', 100)
        ->assertJsonPath('matches.0.entity_id', '1001');
});

it('confirms and syncs a candidate over the api', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = sihandalReadyDocument();
    $match = pendingMatch($document);

    $token = sihandalUser()->createToken('machine-client');

    post("/api/v1/documents/{$document->id}/sihandal/matches/{$match->id}/confirm", [], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('synced', true)
        ->assertJsonPath('sync.status', 'success')
        ->assertJsonPath('document_status', 'synced');

    expect($document->refresh()->status)->toBe(DocumentStatus::Synced)
        ->and($match->refresh()->status)->toBe(SihandalMatchStatus::Confirmed);
});
