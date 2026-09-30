<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\Opd;
use App\Models\User;
use App\Services\Ai\AIProviderInterface;
use App\Services\Ai\AnthropicProvider;
use App\Services\Ai\FakeAIProvider;
use App\Services\Ai\GoogleProvider;
use App\Services\Ai\OpenAIProvider;
use App\Services\Extraction\ExtractionSchemaRegistry;
use App\Services\Ocr\FakeOcrProvider;
use App\Services\Ocr\OcrProviderInterface;
use App\Services\Ocr\TesseractProvider;
use App\Services\Sihandal\FakeSihandalClient;
use App\Services\Sihandal\SihandalApiInterface;
use App\Services\Sihandal\SihandalClient;
use App\Services\Validation\Rules\DuplicateScanRule;
use App\Services\Validation\Rules\FiscalYearMismatchRule;
use App\Services\Validation\Rules\LowConfidenceRule;
use App\Services\Validation\Rules\MissingDocumentNumberRule;
use App\Services\Validation\Rules\TotalMismatchRule;
use App\Services\Validation\ValidationEngine;
use App\Services\Validation\ValidationRule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->bindAiProvider();
        $this->bindOcrProvider();
        $this->bindSihandalClient();
        $this->bindValidationEngine();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Stable, short morph aliases so audit_logs.subject_type (and any
        // future morph map) reads as "document", not a fully-qualified class.
        Relation::enforceMorphMap([
            'document' => Document::class,
            'user' => User::class,
            'opd' => Opd::class,
        ]);

        // Auto-start processing after upload (spec §30). QueueDocumentProcessing
        // is auto-discovered (typed handle(DocumentUploaded) signature) — do
        // NOT also call Event::listen here, or the pipeline starts twice.
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * AI provider is selected by config; unconfigured providers fall back to
     * the fake implementation so the app stays runnable offline (spec §34).
     */
    private function bindAiProvider(): void
    {
        $this->app->bind(AIProviderInterface::class, function (Application $app) {
            /** @var array<string, mixed> $providers */
            $providers = config('agent-finance.ai.providers', []);

            return match (config('agent-finance.ai.provider')) {
                'fake' => $app->make(FakeAIProvider::class),
                'openai' => ($providers['openai']['key'] ?? null)
                    ? $app->make(OpenAIProvider::class)
                    : $app->make(FakeAIProvider::class),
                'anthropic' => ($providers['anthropic']['key'] ?? null)
                    ? $app->make(AnthropicProvider::class)
                    : $app->make(FakeAIProvider::class),
                'google' => ($providers['google']['key'] ?? null)
                    ? $app->make(GoogleProvider::class)
                    : $app->make(FakeAIProvider::class),
                // "local" = any OpenAI-compatible self-hosted endpoint.
                'local' => filled($providers['local']['base_url'] ?? null)
                    ? $app->make(OpenAIProvider::class)
                    : $app->make(FakeAIProvider::class),
                default => $app->make(FakeAIProvider::class),
            };
        });
    }

    /**
     * OCR provider selection (spec §9).
     */
    private function bindOcrProvider(): void
    {
        $this->app->bind(OcrProviderInterface::class, function (Application $app) {
            return match (config('agent-finance.ocr.driver')) {
                'fake' => $app->make(FakeOcrProvider::class),
                'tesseract' => $app->make(TesseractProvider::class),
                default => $app->make(FakeOcrProvider::class),
            };
        });
    }

    /**
     * Deterministic validation engine (spec §15): pure rules, no AI. The rule
     * list is the extension point — new rules are registered here, never
     * hardcoded into jobs.
     */
    private function bindValidationEngine(): void
    {
        $this->app->singleton(ValidationEngine::class, function (Application $app) {
            /** @var array<int, class-string<ValidationRule>> $rules */
            $rules = [
                TotalMismatchRule::class,
                FiscalYearMismatchRule::class,
                MissingDocumentNumberRule::class,
                LowConfidenceRule::class,
                DuplicateScanRule::class,
            ];

            return new ValidationEngine(
                rules: array_map(fn ($rule) => $app->make($rule), $rules),
                highConfidence: (float) config('agent-finance.validation.review_confidence', 0.85) * 100,
                lowConfidence: (float) config('agent-finance.validation.low_confidence', 0.60) * 100,
                registry: $app->make(ExtractionSchemaRegistry::class),
            );
        });
    }

    /**
     * SIHANDAL client: real HTTP client only when a base URL is configured,
     * otherwise the fake client keeps development/tests offline (spec §16).
     */
    private function bindSihandalClient(): void
    {
        $this->app->bind(SihandalApiInterface::class, function (Application $app) {
            if (filled(config('agent-finance.sihandal.base_url'))) {
                return $app->make(SihandalClient::class);
            }

            return $app->make(FakeSihandalClient::class);
        });
    }
}
