<?php

use App\Enums\UserRole;
use App\Models\DocumentType;
use App\Models\Opd;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AgentFinanceSeeder;

it('seeds the four platform roles', function () {
    $this->seed(AgentFinanceSeeder::class);

    foreach (UserRole::slugs() as $slug) {
        expect(Role::query()->where('slug', $slug)->exists())->toBeTrue("Missing role: {$slug}");
    }
});

it('seeds sample OPDs with unique codes', function () {
    $this->seed(AgentFinanceSeeder::class);

    $codes = Opd::query()->pluck('code');

    expect($codes->count())->toBeGreaterThan(0)
        ->and($codes->unique()->count())->toBe($codes->count());
});

it('seeds the extensible document type catalog', function () {
    $this->seed(AgentFinanceSeeder::class);

    // Spec §6 catalog: DPA, RKA, SPM, SP2D, BKU, Kwitansi, Invoice, ... Other.
    expect(DocumentType::query()->where('code', 'spm')->exists())->toBeTrue()
        ->and(DocumentType::query()->where('code', 'sp2d')->exists())->toBeTrue()
        ->and(DocumentType::query()->where('code', 'bku')->exists())->toBeTrue()
        ->and(DocumentType::query()->where('code', 'other')->exists())->toBeTrue()
        ->and(DocumentType::query()->count())->toBeGreaterThanOrEqual(20);
});

it('seeds an active super admin account', function () {
    $this->seed(AgentFinanceSeeder::class);

    $admin = User::query()
        ->whereHas('role', fn ($q) => $q->where('slug', UserRole::SuperAdmin->value))
        ->first();

    expect($admin)->not->toBeNull()
        ->and($admin->is_active)->toBeTrue();
});

it('is idempotent when re-run', function () {
    $this->seed(AgentFinanceSeeder::class);
    $this->seed(AgentFinanceSeeder::class);

    expect(Role::query()->count())->toBe(4);
});
