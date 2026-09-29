<?php

namespace App\Providers;

use App\Services\Ai\AIProviderInterface;
use App\Services\Ai\AnthropicProvider;
use App\Services\Ai\FakeAIProvider;
use App\Services\Ai\GoogleProvider;
use App\Services\Ai\OpenAIProvider;
use Carbon\CarbonImmutable;
use App\Services\Ocr\FakeOcrProvider;
use App\Services\Ocr\OcrProviderInterface;
use App\Services\Sihandal\FakeSihandalClient;
use App\Services\Sihandal\SihandalApiInterface;
use Illuminate\Contracts\Foundation\Application;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Auto-start processing after upload (spec §30). The listener runs on
        // the queue so HTTP responses stay fast.
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\DocumentUploaded::class,
            \App\Listeners\QueueDocumentProcessing::class,
        );
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
                'tesseract' => $app->make(\App\Services\Ocr\TesseractProvider::class),
                default => $app->make(FakeOcrProvider::class),
            };
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
                return $app->make(\App\Services\Sihandal\SihandalClient::class);
            }

            return $app->make(FakeSihandalClient::class);
        });
    }
}
