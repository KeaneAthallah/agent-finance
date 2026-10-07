<?php

use App\Enums\UserRole;
use App\Models\AiRun;
use App\Services\Ai\AiChatRequest;
use App\Services\Ai\AiChatResult;
use App\Services\Ai\AiDocumentRequest;
use App\Services\Ai\AiDocumentResult;
use App\Services\Ai\AiEmbeddingResult;
use App\Services\Ai\AiExtractionRequest;
use App\Services\Ai\AiExtractionResult;
use App\Services\Ai\AIProviderInterface;
use App\Services\Ai\ChatStatement;
use Database\Seeders\AgentFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

it('repairs a non-JSON answer once using the JSON repair instruction', function () {
    $provider = new class implements AIProviderInterface
    {
        public int $attempts = 0;

        public function analyzeDocument(AiDocumentRequest $request): AiDocumentResult
        {
            throw new RuntimeException('Not used in this test.');
        }

        public function extractStructuredData(AiExtractionRequest $request): AiExtractionResult
        {
            throw new RuntimeException('Not used in this test.');
        }

        public function generateEmbedding(string $text, string $model): AiEmbeddingResult
        {
            throw new RuntimeException('Not used in this test.');
        }

        public function chat(AiChatRequest $request): AiChatResult
        {
            $this->attempts++;

            if ($this->attempts === 1) {
                // Prose instead of the JSON contract (§47).
                return new AiChatResult(content: 'Maaf, data tidak tersedia dalam bentuk ini.', meta: ['provider' => 'stub']);
            }

            // The repair call must ask for strict JSON.
            $last = collect($request->messages)->last()['content'] ?? '';
            expect($last)->toContain('not a valid JSON object');

            return new AiChatResult(
                content: json_encode([
                    'summary' => 'Selesai.',
                    'statements' => [
                        ['label' => ChatStatement::WARNING, 'text' => 'Tidak ada data untuk tahun tersebut.', 'source' => null],
                    ],
                ]),
                meta: ['provider' => 'stub'],
            );
        }
    };

    app()->instance(AIProviderInterface::class, $provider);

    $this->seed(AgentFinanceSeeder::class);
    actingAs(testUser(UserRole::Verifier->value, testOpd()));

    post('/ai-assistant/chat', [
        'question' => 'test',
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('summary', 'Selesai.')
        ->assertJsonPath('statements.0.label', 'WARNING');

    expect($provider->attempts)->toBe(2)
        // The repair round is observable in ai_runs.meta (§51).
        ->and(AiRun::query()->latest('id')->value('meta'))->toBeArray()
        ->and(AiRun::query()->latest('id')->firstOrFail()->meta['json_retry'])->toBeTrue();
});

it('falls back to an AI-interpretation answer after two prose failures', function () {
    $provider = new class implements AIProviderInterface
    {
        public function analyzeDocument(AiDocumentRequest $request): AiDocumentResult
        {
            throw new RuntimeException('Not used in this test.');
        }

        public function extractStructuredData(AiExtractionRequest $request): AiExtractionResult
        {
            throw new RuntimeException('Not used in this test.');
        }

        public function generateEmbedding(string $text, string $model): AiEmbeddingResult
        {
            throw new RuntimeException('Not used in this test.');
        }

        public function chat(AiChatRequest $request): AiChatResult
        {
            return new AiChatResult(content: 'Hanya kalimat biasa.', meta: ['provider' => 'stub']);
        }
    };

    app()->instance(AIProviderInterface::class, $provider);

    $this->seed(AgentFinanceSeeder::class);
    actingAs(testUser(UserRole::Verifier->value, testOpd()));

    post('/ai-assistant/chat', [
        'question' => 'test',
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('summary', 'Hanya kalimat biasa.')
        ->assertJsonPath('statements.0.label', 'AI INTERPRETATION');
});
