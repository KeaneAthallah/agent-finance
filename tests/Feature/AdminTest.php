<?php

use App\Enums\UserRole;
use App\Models\AiPrompt;
use App\Models\AuditLog;
use App\Models\DocumentType;
use App\Models\Opd;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AgentFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function adminUser(): User
{
    return testUser(UserRole::Admin->value, testOpd());
}

function superAdminUser(): User
{
    return testUser(UserRole::SuperAdmin->value, null);
}

it('renders the administration surface to admins only', function () {
    $this->seed(AgentFinanceSeeder::class);

    actingAs(adminUser());

    get('/administration')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/index')
            ->where('auth.can.manage', false));

    // Super admins may mutate.
    actingAs(superAdminUser());

    get('/administration')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/index')
            ->where('auth.can.manage', true));

    // Verifiers never see the platform surface.
    actingAs(testUser(UserRole::Verifier->value, testOpd()));

    get('/administration')->assertForbidden();
    get('/settings')->assertForbidden();
});

it('renders the settings surface with the effective configuration', function () {
    $this->seed(AgentFinanceSeeder::class);

    actingAs(adminUser());

    get('/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/index')
            ->where('settings.ai.provider', 'fake')
            ->where('settings.validation.review_confidence', 0.85));
});

it('lists users, roles, opds, document types and prompt versions', function () {
    $this->seed(AgentFinanceSeeder::class);

    AiPrompt::query()->create([
        'name' => 'financial_extraction',
        'version' => '1.0',
        'system_prompt' => 'Original prompt.',
        'model' => 'gpt-4o-mini',
        'temperature' => 0.1,
        'is_active' => true,
    ]);

    actingAs(adminUser());

    get('/administration')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users', fn ($users) => $users->contains(
                fn ($user) => $user['email'] === 'admin@agentfinance.test',
            ))
            ->where('roles', fn ($roles) => $roles->contains(
                fn ($role) => $role['slug'] === 'verifier',
            ))
            ->where('opds', fn ($opds) => $opds->contains(
                fn ($opd) => $opd['code'] === '4.01.0',
            ))
            ->where('documentTypes', fn ($types) => $types->contains(
                fn ($type) => $type['code'] === 'spm',
            ))
            ->where('prompts.financial_extraction', fn ($versions) => $versions->contains(
                fn ($version) => $version['version'] === '1.0'
                    && $version['is_active'] === true,
            )));
});

it('creates an immutable prompt version and activates it', function () {
    $this->seed(AgentFinanceSeeder::class);

    $original = AiPrompt::query()->create([
        'name' => 'financial_extraction',
        'version' => '1.0',
        'system_prompt' => 'Original prompt.',
        'model' => 'gpt-4o-mini',
        'temperature' => 0.1,
        'is_active' => true,
    ]);

    actingAs(superAdminUser());

    post('/administration/prompts', [
        'name' => 'financial_extraction',
        'version' => '2.0',
        'system_prompt' => 'Revised prompt.',
        'model' => 'gpt-4o-mini',
        'temperature' => 0.2,
    ], ['Accept' => 'application/json'])
        ->assertOk();

    // The new version is active; the original row is
    // untouched and inactive (§33 immutability).
    $original->refresh();
    $newVersion = AiPrompt::query()
        ->where('name', 'financial_extraction')
        ->where('version', '2.0')
        ->sole();

    expect($newVersion->is_active)->toBeTrue()
        ->and($original->is_active)->toBeFalse()
        ->and($original->system_prompt)->toBe('Original prompt.')
        ->and(AuditLog::query()->where('action', 'prompt.created')->exists())->toBeTrue();
});

it('activates an existing prompt version without rewriting rows', function () {
    $this->seed(AgentFinanceSeeder::class);

    $v1 = AiPrompt::query()->create([
        'name' => 'assistant_chat',
        'version' => '1.0',
        'system_prompt' => 'First.',
        'is_active' => false,
    ]);

    $v2 = AiPrompt::query()->create([
        'name' => 'assistant_chat',
        'version' => '2.0',
        'system_prompt' => 'Second.',
        'is_active' => true,
    ]);

    actingAs(superAdminUser());

    post("/administration/prompts/{$v1->id}/activate", [], ['Accept' => 'application/json'])
        ->assertOk();

    expect($v1->refresh()->is_active)->toBeTrue()
        ->and($v2->refresh()->is_active)->toBeFalse();
});

it('rejects duplicate prompt versions and non-super-admin mutations', function () {
    $this->seed(AgentFinanceSeeder::class);

    AiPrompt::query()->create([
        'name' => 'financial_extraction',
        'version' => '1.0',
        'system_prompt' => 'Original.',
        'is_active' => true,
    ]);

    // A plain admin may view but never mutate (§36, §44).
    actingAs(adminUser());

    post('/administration/prompts', [
        'name' => 'financial_extraction',
        'version' => '2.0',
        'system_prompt' => 'Revised.',
    ], ['Accept' => 'application/json'])->assertForbidden();

    // Super admins cannot create a duplicate (name, version).
    actingAs(superAdminUser());

    post('/administration/prompts', [
        'name' => 'financial_extraction',
        'version' => '1.0',
        'system_prompt' => 'Duplicate.',
    ])->assertSessionHasErrors('version');
});

it('manages users as super admin only', function () {
    $this->seed(AgentFinanceSeeder::class);

    $target = testUser(UserRole::Viewer->value, testOpd());
    $verifierRole = Role::query()->where('slug', UserRole::Verifier->value)->sole();
    $otherOpd = Opd::query()->create(['code' => '8.8.0', 'name' => 'Other OPD']);

    // Plain admin: forbidden.
    actingAs(adminUser());

    post("/administration/users/{$target->id}", [
        'role_id' => $verifierRole->id,
    ], ['Accept' => 'application/json'])->assertForbidden();

    // Super admin: role, OPD and active flag all move.
    actingAs(superAdminUser());

    post("/administration/users/{$target->id}", [
        'role_id' => $verifierRole->id,
        'opd_id' => $otherOpd->id,
        'is_active' => false,
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('user.is_active', false);

    $target->refresh();

    expect($target->role_id)->toBe($verifierRole->id)
        ->and($target->opd_id)->toBe($otherOpd->id)
        ->and($target->is_active)->toBeFalse()
        ->and(AuditLog::query()->where('action', 'user.updated')->where('subject_id', $target->id)->exists())->toBeTrue();
});

it('creates and updates opds as super admin only', function () {
    $this->seed(AgentFinanceSeeder::class);

    // Plain admin: forbidden.
    actingAs(adminUser());

    post('/administration/opds', [
        'code' => '9.9.0',
        'name' => 'New OPD',
    ], ['Accept' => 'application/json'])->assertForbidden();

    // Super admin: creates, then renames and deactivates.
    actingAs(superAdminUser());

    post('/administration/opds', [
        'code' => '9.9.0',
        'name' => 'New OPD',
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('opd.code', '9.9.0');

    $opd = Opd::query()->where('code', '9.9.0')->sole();

    post("/administration/opds/{$opd->id}", [
        'name' => 'Renamed OPD',
        'is_active' => false,
    ], ['Accept' => 'application/json'])->assertOk();

    expect($opd->refresh()->name)->toBe('Renamed OPD')
        ->and($opd->is_active)->toBeFalse();

    // Duplicate codes are rejected.
    post('/administration/opds', [
        'code' => '9.9.0',
        'name' => 'Duplicate',
    ])->assertSessionHasErrors('code');
});

it('toggles document types as super admin only', function () {
    $this->seed(AgentFinanceSeeder::class);

    $type = DocumentType::query()->where('code', 'spm')->sole();

    actingAs(adminUser());

    post("/administration/document-types/{$type->id}", [
        'is_active' => false,
    ], ['Accept' => 'application/json'])->assertForbidden();

    actingAs(superAdminUser());

    post("/administration/document-types/{$type->id}", [
        'is_active' => false,
    ], ['Accept' => 'application/json'])->assertOk();

    expect($type->refresh()->is_active)->toBeFalse()
        ->and(AuditLog::query()->where('action', 'document_type.updated')->exists())->toBeTrue();
});
