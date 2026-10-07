<?php

use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use App\Models\AiConversationMessage;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\Ai\AiChatRequest;
use App\Services\Ai\AiChatResult;
use App\Services\Ai\AiDocumentRequest;
use App\Services\Ai\AiDocumentResult;
use App\Services\Ai\AiEmbeddingResult;
use App\Services\Ai\AiExtractionRequest;
use App\Services\Ai\AiExtractionResult;
use App\Services\Ai\AIProviderInterface;
use Database\Seeders\AgentFinanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

/**
 * @return array<int, array{event: string, data: array<string, mixed>|string}>
 */
function sseEvents(string $body): array
{
    $events = [];

    foreach (preg_split('/\R{2,}/', trim($body)) ?: [] as $block) {
        $event = 'message';
        $data = '';

        foreach (explode("\n", $block) as $line) {
            if (str_starts_with($line, 'event:')) {
                $event = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data .= trim(substr($line, 5));
            }
        }

        $decoded = json_decode($data, true);

        $events[] = ['event' => $event, 'data' => is_array($decoded) ? $decoded : $data];
    }

    return $events;
}

function streamChatUser(): User
{
    return testUser(UserRole::Verifier->value, testOpd());
}

function streamReadyDocument(): Document
{
    $document = Document::factory()
        ->for(testOpd(), 'opd')
        ->for(DocumentType::query()->where('code', 'spm')->firstOrFail(), 'documentType')
        ->create([
            'document_number' => 'SPM-800/2026',
            'fiscal_year' => 2026,
            'status' => DocumentStatus::Approved,
        ]);

    return $document;
}

/** @return array<int, array{event: string, data: array<string, mixed>}> */
function streamDoneData(string $body): array
{
    return array_values(array_filter(
        sseEvents($body),
        static fn (array $event): bool => $event['event'] === 'done' && is_array($event['data']),
    ));
}

function streamedContent(TestResponse $response): string
{
    ob_start();
    $response->baseResponse->sendContent();

    return ob_get_clean() ?: '';
}

it('streams a budget answer through SSE with tool progress, deltas and a done event', function () {
    $this->seed(AgentFinanceSeeder::class);

    actingAs(streamChatUser());

    $response = post('/ai-assistant/chat/stream', [
        'question' => 'Berapa total anggaran TA 2026?',
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertHeaderContains('content-type', 'text/event-stream');

    $body = streamedContent($response);
    $events = sseEvents($body);

    // Tool progress surfaced before the final answer.
    expect(collect($events)->firstWhere('event', 'tool')['data']['name'])->toBe('get_budget_summary')
        ->and(collect($events)->contains('event', 'delta'))->toBeTrue();

    $done = streamDoneData($body);

    expect($done)->toHaveCount(1)
        ->and($done[0]['data']['summary'])->toBeString()
        ->and($done[0]['data']['tool_calls'][0]['name'])->toBe('get_budget_summary')
        ->and($done[0]['data']['statements'])->toBeArray()
        ->and($done[0]['data']['conversation_id'])->toBeInt();
});

it('streams document search citations as source refs in the done event', function () {
    $this->seed(AgentFinanceSeeder::class);

    $document = streamReadyDocument();

    $document->chunks()->create([
        'version' => 1,
        'chunk_index' => 0,
        'content' => 'Belanja bahan habis pakai untuk kegiatan sekolah dasar',
        'embedding' => app(AIProviderInterface::class)
            ->generateEmbedding('Belanja bahan habis pakai', 'fake')->vector,
        'opd_id' => $document->opd_id,
        'fiscal_year' => 2026,
        'document_type' => 'spm',
    ]);

    actingAs(streamChatUser());

    $body = streamedContent(post('/ai-assistant/chat/stream', [
        'question' => 'cari dokumen tentang bahan habis pakai',
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertHeaderContains('content-type', 'text/event-stream'));

    $done = streamDoneData($body);

    expect($done)->toHaveCount(1)
        ->and($done[0]['data']['tool_calls'][0]['name'])->toBe('search_documents')
        ->and(collect($done[0]['data']['meta']['source_refs'])->firstWhere('id', $document->id))
        ->not->toBeNull();
});

it('falls back to a non-streaming provider without breaking the SSE contract', function () {
    $this->seed(AgentFinanceSeeder::class);

    app()->instance(AIProviderInterface::class, new class implements AIProviderInterface
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
            return new AiChatResult(
                content: json_encode([
                    'summary' => 'Jawaban langsung tanpa streaming.',
                    'statements' => [
                        ['label' => 'AI INTERPRETATION', 'text' => 'Ringkasan.', 'source' => null],
                    ],
                ]),
                meta: ['provider' => 'stub'],
            );
        }
    });

    actingAs(streamChatUser());

    $body = streamedContent(post('/ai-assistant/chat/stream', [
        'question' => 'test',
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertHeaderContains('content-type', 'text/event-stream'));

    $done = streamDoneData($body);

    expect($done)->toHaveCount(1)
        ->and($done[0]['data']['summary'])->toBe('Jawaban langsung tanpa streaming.')
        ->and($done[0]['data']['statements'])->toBeArray();
});

it('persists the streamed turn into the conversation', function () {
    $this->seed(AgentFinanceSeeder::class);

    actingAs(streamChatUser());

    $body = streamedContent(post('/ai-assistant/chat/stream', [
        'question' => 'Berapa total anggaran TA 2026?',
    ], ['Accept' => 'application/json'])
        ->assertOk());

    $conversationId = streamDoneData($body)[0]['data']['conversation_id'];

    expect(AiConversationMessage::query()
        ->where('conversation_id', $conversationId)
        ->count())->toBe(2);
});
