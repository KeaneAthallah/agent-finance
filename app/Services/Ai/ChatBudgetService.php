<?php

namespace App\Services\Ai;

use App\Exceptions\BudgetExceededException;
use App\Models\AiConversationMessage;
use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Per-user daily AI spend guardrails (roadmap P0-2): a token budget from
 * ai_usage and a message-count limit from persisted conversations. Both are
 * config-driven; when either is reached, ChatService refuses the turn with a
 * friendly message instead of spending more tokens.
 */
final class ChatBudgetService
{
    public function assertWithinBudget(User $user): void
    {
        $this->assertTokenBudget($user);

        $this->assertMessageLimit($user);
    }

    private function assertTokenBudget(User $user): void
    {
        $tokens = AiUsage::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', today()->startOfDay())
            ->sum(DB::raw('coalesce(input_tokens, 0) + coalesce(output_tokens, 0)'));

        $budget = (int) config('agent-finance.ai.chat.daily_token_budget', 200_000);

        if ($tokens >= $budget) {
            throw new BudgetExceededException(
                'Your daily AI budget for today has been used up. Please try again tomorrow.',
            );
        }
    }

    private function assertMessageLimit(User $user): void
    {
        $messages = AiConversationMessage::query()
            ->whereHas('conversation', fn (Builder $query): Builder => $query->where('user_id', $user->id))
            ->where('created_at', '>=', today()->startOfDay())
            ->count();

        $limit = (int) config('agent-finance.ai.chat.daily_message_limit', 100);

        if ($messages >= $limit) {
            throw new BudgetExceededException(
                'Your daily message limit has been reached. Please try again tomorrow.',
            );
        }
    }
}
