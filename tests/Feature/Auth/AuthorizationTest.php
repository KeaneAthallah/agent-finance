<?php

use App\Enums\UserRole;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('hides the dashboard from guests', function () {
    get('/dashboard')->assertRedirect(route('login'));
});

it('allows any active user to open the dashboard', function () {
    actingAs(testUser(UserRole::Viewer->value, testOpd()))
        ->get(route('dashboard'))
        ->assertOk();
});

it('allows admins and verifiers into document sections', function (string $role, int $status) {
    actingAs(testUser($role, testOpd()))
        ->get(route('documents.index'))
        ->assertStatus($status);
})->with([
    ['admin', 200],
    ['verifier', 200],
    ['viewer', 403],
    ['super_admin', 200],
]);

it('blocks viewers from the review queue', function () {
    actingAs(testUser(UserRole::Viewer->value, testOpd()))
        ->get(route('review.index'))
        ->assertForbidden();
});

it('allows verifiers into the review queue', function () {
    actingAs(testUser(UserRole::Verifier->value, testOpd()))
        ->get(route('review.index'))
        ->assertOk();
});

it('blocks non-admins from administration', function () {
    actingAs(testUser(UserRole::Verifier->value, testOpd()))
        ->get(route('admin.index'))
        ->assertForbidden();
});

it('allows only admins into administration', function () {
    actingAs(testUser(UserRole::SuperAdmin->value, null))
        ->get(route('admin.index'))
        ->assertOk();
});

it('returns the placeholder payload for later-phase sections', function () {
    actingAs(testUser(UserRole::Verifier->value, testOpd()))
        ->get(route('anomalies.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('placeholder')
                ->where('title', 'Anomalies')
                ->where('phase', 'Phase 8 — Anomalies & Financial Analysis'),
        );
});
