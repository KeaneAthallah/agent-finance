<?php

use App\Enums\UserRole;
use App\Models\Document;
use App\Services\Security\ClamAvScanner;
use App\Services\Security\FakeMalwareScanner;
use App\Services\Security\MalwareScannerInterface;
use Database\Seeders\AgentFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

/**
 * Scanner double that reports every upload infected.
 */
final class InfectedScanner implements MalwareScannerInterface
{
    public function isClean(string $path): bool
    {
        unset($path);

        return false;
    }

    public function driver(): string
    {
        return 'test-infected';
    }
}

it('applies security headers to every web response', function () {
    get('/login')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy')
        ->assertHeader('Content-Security-Policy');
});

it('enforces a strict content security policy', function () {
    config(['app.env' => 'production']);

    $response = get('/login');

    $csp = (string) $response->headers->get('Content-Security-Policy');
    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("form-action 'self'")
        ->not->toContain('unsafe-inline')
        ->and($csp)
        ->not->toContain('unsafe-eval');
});

it('keeps framing locked down in the permissive local policy', function () {
    config(['app.env' => 'local']);

    $response = get('/login');

    $csp = (string) $response->headers->get('Content-Security-Policy');
    expect($csp)
        ->toContain("frame-ancestors 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'");
});

it('throttles the login form and the AI chat', function () {
    $routes = Route::getRoutes();

    $login = $routes->getByName('login.store');
    $chat = $routes->getByName('ai.assistant.chat');

    \assert($login instanceof Illuminate\Routing\Route);
    \assert($chat instanceof Illuminate\Routing\Route);

    expect($login->gatherMiddleware())->toContain('throttle:5,1')
        // Chat throttling is config-driven via the named limiter (§35).
        ->and($chat->gatherMiddleware())->toContain('throttle:ai-assistant');
});

it('binds the malware scanner from configuration', function () {
    $this->seed(AgentFinanceSeeder::class);

    config(['agent-finance.security.malware_driver' => 'fake']);
    expect(app(MalwareScannerInterface::class))->toBeInstanceOf(FakeMalwareScanner::class);

    config(['agent-finance.security.malware_driver' => 'clamav']);
    expect(app(MalwareScannerInterface::class))->toBeInstanceOf(ClamAvScanner::class);
});

it('rejects an infected upload before anything is persisted (§7)', function () {
    $this->seed(AgentFinanceSeeder::class);

    Storage::fake('documents');

    $this->app->instance(MalwareScannerInterface::class, new InfectedScanner);

    $user = testUser(UserRole::Verifier->value, testOpd());

    actingAs($user);

    post(route('documents.store'), [
        'file' => UploadedFile::fake()->createWithContent(
            'spm_2026.pdf',
            minimalPdf(),
        ),
        'fiscal_year' => 2026,
    ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('message', '"spm_2026.pdf" failed the malware scan.');

    // Infected files never reach storage or the documents table.
    expect(Document::query()->count())->toBe(0)
        ->and(Storage::disk('documents')->allFiles())->toBeEmpty();
});

it('keeps secrets out of the repository', function () {
    // .env must never be tracked.
    expect(file_get_contents(base_path('.gitignore')))->toMatch('/\.env/');

    $tracked = Process::run(['git', 'ls-files'])->output();

    foreach (explode("\n", trim($tracked)) as $file) {
        if ($file === '' || ! is_file($file)) {
            continue;
        }

        $contents = (string) file_get_contents($file);

        expect($contents)->not->toMatch(
            '/BEGIN (RSA |EC |OPENSSH |DSA )?PRIVATE KEY/',
            "Private key material must not be committed: {$file}",
        );
    }
});
