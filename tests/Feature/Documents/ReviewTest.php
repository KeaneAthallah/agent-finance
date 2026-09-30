<?php

use App\Actions\ReviewDocumentAction;
use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\AgentFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function reviewerUser(): User
{
    return testUser(UserRole::Verifier->value, testOpd());
}

function reviewableDocument(?User $forUser = null): Document
{
    $user = $forUser ?? reviewerUser();

    return Document::factory()
        ->for($user->opd, 'opd')
        ->create(['status' => DocumentStatus::ReviewRequired]);
}

it('lists review_required documents with anomaly counts', function () {
    $this->seed(AgentFinanceSeeder::class);

    $user = reviewerUser();
    actingAs($user);

    $document = reviewableDocument($user);
    $document->anomalies()->create([
        'type' => 'missing_document_number',
        'severity' => 'high',
        'status' => 'open',
        'title' => 'Document number missing',
    ]);

    get(route('review.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('review/index')
            ->has('documents.data', 1)
            ->where('auth.can.review', true));
});

it('scopes the review queue to the user OPD', function () {
    $this->seed(AgentFinanceSeeder::class);

    $user = reviewerUser();
    actingAs($user);

    // Another OPD's flagged document must not appear.
    Document::factory()->create(['status' => DocumentStatus::ReviewRequired]);

    get(route('review.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('documents.data', 0));
});

it('approves a document as a verifier', function () {
    $this->seed(AgentFinanceSeeder::class);

    $user = reviewerUser();
    actingAs($user);

    $document = reviewableDocument($user);

    post(route('documents.review', $document), [
        'decision' => 'approved',
        'notes' => 'Checked against SPP; numbers match.',
    ])->assertRedirect();

    expect($document->refresh()->status)->toBe(DocumentStatus::Approved)
        ->and($document->reviews()->sole()->decision->value)->toBe('approved')
        ->and($document->reviews()->sole()->notes)->toBe('Checked against SPP; numbers match.')
        ->and($document->reviews()->sole()->reviewer_id)->toBe($user->id);
});

it('rejects a document as a verifier', function () {
    $this->seed(AgentFinanceSeeder::class);

    $user = reviewerUser();
    actingAs($user);

    $document = reviewableDocument($user);

    post(route('documents.review', $document), ['decision' => 'rejected'])->assertRedirect();

    expect($document->refresh()->status)->toBe(DocumentStatus::Rejected)
        ->and($document->reviews()->sole()->decision->value)->toBe('rejected');
});

it('restarts the pipeline with a new version on re-analysis', function () {
    $this->seed(AgentFinanceSeeder::class);

    // No stored file fixture needed: the dispatched head job is faked.
    Bus::fake();

    $user = reviewerUser();
    actingAs($user);

    $document = reviewableDocument($user);
    $document->processingRuns()->create(['version' => 1, 'stage' => 'pipeline', 'status' => 'completed']);

    post(route('documents.review', $document), [
        'decision' => 'reanalysis_requested',
        'notes' => 'Amount looks wrong, re-run extraction.',
    ])->assertRedirect();

    expect($document->refresh()->status)->toBe(DocumentStatus::Queued)
        ->and((int) $document->processingRuns()->max('version'))->toBe(2)
        ->and($document->reviews()->sole()->decision->value)->toBe('reanalysis_requested');
});

it('rejects an invalid decision value', function () {
    $this->seed(AgentFinanceSeeder::class);

    actingAs(reviewerUser());

    $document = reviewableDocument();

    post(route('documents.review', $document), ['decision' => 'maybe'])
        ->assertRedirect()
        ->assertSessionHasErrors('decision');

    expect($document->reviews()->count())->toBe(0);
});

it('forbids viewers from reviewing', function () {
    $this->seed(AgentFinanceSeeder::class);

    $viewer = testUser(UserRole::Viewer->value, testOpd());
    actingAs($viewer);

    $document = reviewableDocument();

    post(route('documents.review', $document), ['decision' => 'approved'])->assertForbidden();

    expect($document->refresh()->status)->toBe(DocumentStatus::ReviewRequired)
        ->and($document->reviews()->count())->toBe(0);
});

it('forbids reviewing a document outside the user OPD', function () {
    $this->seed(AgentFinanceSeeder::class);

    actingAs(reviewerUser());

    // Belongs to a different OPD (factory default).
    $document = Document::factory()->create(['status' => DocumentStatus::ReviewRequired]);

    post(route('documents.review', $document), ['decision' => 'approved'])->assertForbidden();
});

it('audits the review decision as a human action', function () {
    $this->seed(AgentFinanceSeeder::class);

    $user = reviewerUser();
    actingAs($user);

    $document = reviewableDocument($user);

    post(route('documents.review', $document), ['decision' => 'approved'])->assertRedirect();

    $entry = AuditLog::query()
        ->where('action', 'document.reviewed')
        ->where('subject_id', $document->id)
        ->sole();

    expect($entry->actor_type)->toBe('human')
        ->and($entry->user_id)->toBe($user->id)
        ->and($entry->properties['decision'])->toBe('approved');
});

it('snapshots the extraction the reviewer decided on', function () {
    $this->seed(AgentFinanceSeeder::class);

    $user = reviewerUser();
    actingAs($user);

    $document = reviewableDocument($user);

    $extraction = $document->extractions()->create([
        'version' => 3,
        'schema_name' => 'spm',
        'schema_version' => '1.0',
        'payload' => ['document_number' => '800/123/SPM/2026'],
        'status' => 'completed',
        'confidence' => 90,
    ]);

    app(ReviewDocumentAction::class)->execute(
        document: $document,
        reviewer: $user,
        decision: 'approved',
        notes: null,
    );

    $review = $document->reviews()->sole();

    expect($review->extraction_version)->toBe(3)
        ->and($review->extraction_snapshot['document_number'])->toBe('800/123/SPM/2026');
});
