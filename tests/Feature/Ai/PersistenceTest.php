<?php

use App\Enums\UserRole;
use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function aiPersistenceUser(): User
{
    return testUser(UserRole::Verifier->value, testOpd());
}

function startConversation(User $user, string $question = 'Berapa total anggaran TA 2026?'): int
{
    actingAs($user);

    $content = post('/ai-assistant/chat', [
        'question' => $question,
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonStructure(['conversation_id'])->getContent();

    return (int) json_decode($content, true)['conversation_id'];
}

it('persists a conversation titled from its opening question', function () {
    $user = aiPersistenceUser();

    $id = startConversation($user, 'Berapa total anggaran TA 2026?');

    $conversation = AiConversation::query()->findOrFail($id);

    expect($conversation->user_id)->toBe($user->id)
        ->and($conversation->title)->toContain('total anggaran')
        ->and(AiConversationMessage::query()->where('conversation_id', $id)->count())->toBe(2);
});

it('reuses the same conversation and re-appends message rows on follow-ups', function () {
    $user = aiPersistenceUser();

    $id = startConversation($user);

    post('/ai-assistant/chat', [
        'question' => 'Berapa total anggaran TA 2026?',
        'conversation_id' => $id,
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('conversation_id', $id);

    expect(AiConversation::query()->count())->toBe(1)
        ->and(AiConversationMessage::query()->where('conversation_id', $id)->count())->toBe(4);
});

it('serves persisted messages with statements and tool calls', function () {
    $user = aiPersistenceUser();

    $id = startConversation($user);

    get("/ai-assistant/conversations/{$id}/messages", ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'role', 'content', 'statements', 'tool_calls', 'created_at']]])
        ->assertJsonPath('data.0.role', 'user')
        ->assertJsonPath('data.1.role', 'assistant')
        ->assertJsonPath('data.1.tool_calls.0.name', 'get_budget_summary');
});

it('lists the user conversations on the assistant page', function () {
    $user = aiPersistenceUser();

    startConversation($user);

    actingAs($user);

    get('/ai-assistant')
        ->assertInertia(fn ($page) => $page
            ->component('ai/assistant')
            ->has('conversations', 1));
});

it('renames and deletes a conversation with its messages', function () {
    $user = aiPersistenceUser();

    $id = startConversation($user);

    patch("/ai-assistant/conversations/{$id}", [
        'title' => 'Ringkasan anggaran 2026',
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('title', 'Ringkasan anggaran 2026');

    expect(AiConversation::query()->findOrFail($id)->title)->toBe('Ringkasan anggaran 2026');

    delete("/ai-assistant/conversations/{$id}", [], ['Accept' => 'application/json'])
        ->assertNoContent();

    expect(AiConversation::query()->find($id))->toBeNull()
        ->and(AiConversationMessage::query()->where('conversation_id', $id)->count())->toBe(0);
});

it('isolates conversations between users', function () {
    $user = aiPersistenceUser();

    $id = startConversation($user);

    $other = testUser(UserRole::Verifier->value, testOpd());

    actingAs($other);

    get("/ai-assistant/conversations/{$id}/messages", ['Accept' => 'application/json'])
        ->assertNotFound();

    patch("/ai-assistant/conversations/{$id}", [
        'title' => 'tidak boleh',
    ], ['Accept' => 'application/json'])
        ->assertNotFound();

    delete("/ai-assistant/conversations/{$id}", [], ['Accept' => 'application/json'])
        ->assertNotFound();

    post('/ai-assistant/chat', [
        'question' => 'test',
        'conversation_id' => $id,
    ], ['Accept' => 'application/json'])
        ->assertNotFound();

    expect(AiConversation::query()->findOrFail($id)->user_id)->toBe($user->id);
});
