<?php

use App\Enums\UserRole;
use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\AiRun;
use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function aiBudgetUser(): User
{
    return testUser(UserRole::Verifier->value, testOpd());
}

it('refuses chat when the daily message limit is consumed', function () {
    $user = aiBudgetUser();

    $limit = (int) config('agent-finance.ai.chat.daily_message_limit');

    $conversation = AiConversation::query()->create([
        'user_id' => $user->id,
        'title' => 'historic',
    ]);

    for ($i = 0; $i < $limit; $i++) {
        AiConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => "old turn {$i}",
        ]);
    }

    actingAs($user);

    post('/ai-assistant/chat', [
        'question' => 'Berapa total anggaran TA 2026?',
    ], ['Accept' => 'application/json'])
        ->assertStatus(429)
        ->assertJson(['message' => 'Your daily message limit has been reached. Please try again tomorrow.']);
});

it('refuses the streaming endpoint too when budget is consumed', function () {
    $user = aiBudgetUser();

    $limit = (int) config('agent-finance.ai.chat.daily_message_limit');

    $conversation = AiConversation::query()->create([
        'user_id' => $user->id,
        'title' => 'historic',
    ]);

    for ($i = 0; $i < $limit; $i++) {
        AiConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => "old turn {$i}",
        ]);
    }

    actingAs($user);

    post('/ai-assistant/chat/stream', [
        'question' => 'Berapa total anggaran TA 2026?',
    ], ['Accept' => 'application/json'])
        ->assertStatus(429)
        ->assertJson(['message' => 'Your daily message limit has been reached. Please try again tomorrow.']);
});

it('refuses chat when the daily token budget is consumed', function () {
    $user = aiBudgetUser();

    $budget = (int) config('agent-finance.ai.chat.daily_token_budget');

    $run = AiRun::query()->create([
        'stage' => 'chat',
        'provider' => (string) config('agent-finance.ai.provider'),
        'status' => 'completed',
        'meta' => ['user_id' => $user->id],
    ]);

    AiUsage::query()->create([
        'user_id' => $user->id,
        'ai_run_id' => $run->id,
        'provider' => (string) config('agent-finance.ai.provider'),
        'input_tokens' => $budget,
        'output_tokens' => 0,
    ]);

    actingAs($user);

    post('/ai-assistant/chat', [
        'question' => 'Berapa total anggaran TA 2026?',
    ], ['Accept' => 'application/json'])
        ->assertStatus(429)
        ->assertJson(['message' => 'Your daily AI budget for today has been used up. Please try again tomorrow.']);
});

it('does not block a user who is under budget and records usage per user', function () {
    $user = aiBudgetUser();

    actingAs($user);

    post('/ai-assistant/chat', [
        'question' => 'Berapa total anggaran TA 2026?',
    ], ['Accept' => 'application/json'])
        ->assertOk();

    expect(AiUsage::query()->where('user_id', $user->id)->exists())->toBeTrue();
});
