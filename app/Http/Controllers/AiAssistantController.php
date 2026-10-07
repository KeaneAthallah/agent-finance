<?php

namespace App\Http\Controllers;

use App\Exceptions\BudgetExceededException;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\Ai\ChatBudgetService;
use App\Services\Ai\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * AI assistant chat endpoint (spec §19, §20, §47).
 * Read-only by design: the assistant can look up and
 * explain, never write, sync, or approve. Every answer
 * statement carries a trust label (FACT / CALCULATION /
 * AI INTERPRETATION / WARNING).
 *
 * Since roadmap P0-1: conversations are persisted server-side (list, open,
 * rename, delete) and chat context is rebuilt from them; chat is scoped to
 * the authenticated user's own conversations.
 */
final class AiAssistantController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        \assert($user instanceof User);

        return Inertia::render('ai/assistant', [
            'conversations' => AiConversation::query()
                ->where('user_id', $user->id)
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get(['id', 'title', 'updated_at']),
        ]);
    }

    public function chat(Request $request, ChatService $chat): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:1', 'max:2000'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();

        \assert($user instanceof User);

        $conversation = $this->resolveConversation($user, (int) ($validated['conversation_id'] ?? 0), $validated['question']);

        try {
            $answer = $chat->chat(
                $validated['question'],
                $user,
                $validated['history'] ?? [],
                $conversation,
            );
        } catch (BudgetExceededException $exception) {
            // An over-budget user gets a friendly message, never a 500.
            return response()->json(['message' => $exception->getMessage()], 429);
        }

        return response()->json(array_merge($answer->toArray(), [
            'conversation_id' => $conversation->id,
        ]));
    }

    public function stream(Request $request, ChatService $chat, ChatBudgetService $budget): JsonResponse|StreamedResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:1', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();

        \assert($user instanceof User);

        $conversation = $this->resolveConversation($user, (int) ($validated['conversation_id'] ?? 0), $validated['question']);

        // Fail fast with a real 429 before the SSE stream begins, so the
        // client sees a status code instead of only an error event.
        try {
            $budget->assertWithinBudget($user);
        } catch (BudgetExceededException $exception) {
            return response()->json(['message' => $exception->getMessage()], 429);
        }

        return response()->stream(function () use ($chat, $validated, $user, $conversation): void {
            $emit = static function (string $event, string $data): void {
                echo "event: {$event}\n";
                echo 'data: '.$data."\n\n";

                if (PHP_SAPI !== 'cli' && ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            try {
                $answer = $chat->chatStream(
                    question: $validated['question'],
                    user: $user,
                    conversation: $conversation,
                    onEvent: $emit,
                );

                $emit('done', (string) json_encode(array_merge($answer->toArray(), [
                    'conversation_id' => $conversation->id,
                ]), JSON_THROW_ON_ERROR));
            } catch (BudgetExceededException $exception) {
                $emit('error', (string) json_encode(['message' => $exception->getMessage()], JSON_THROW_ON_ERROR));
            } catch (Throwable $exception) {
                Log::warning('AI chat stream error', ['error' => $exception->getMessage()]);

                $emit('error', (string) json_encode(['message' => 'The assistant hit an unexpected error. Please try again.'], JSON_THROW_ON_ERROR));
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    public function messages(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->assertOwner($conversation, $request);

        $messages = $conversation->messages()
            ->orderBy('id')
            ->get(['id', 'role', 'content', 'statements', 'tool_calls', 'created_at']);

        return response()->json(['data' => $messages]);
    }

    public function rename(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->assertOwner($conversation, $request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
        ]);

        $conversation->update(['title' => trim($validated['title'])]);

        return response()->json(['title' => $conversation->title]);
    }

    public function destroy(Request $request, AiConversation $conversation): HttpResponse
    {
        $this->assertOwner($conversation, $request);

        $conversation->delete();

        return response()->noContent();
    }

    /**
     * Reuse (by id, owner-scoped) or create the conversation for this turn.
     * A brand-new conversation is titled from its opening question.
     */
    private function resolveConversation(User $user, int $conversationId, string $question): AiConversation
    {
        if ($conversationId <= 0) {
            return AiConversation::query()->create([
                'user_id' => $user->id,
                'title' => mb_strimwidth($question, 0, 120, '…'),
            ]);
        }

        $conversation = AiConversation::query()
            ->where('user_id', $user->id)
            ->find($conversationId);

        // Untraceable/foreign conversations are 404s: never hint that
        // another user's conversation exists (§36).
        if ($conversation === null) {
            abort(404);
        }

        return $conversation;
    }

    private function assertOwner(AiConversation $conversation, Request $request): void
    {
        $user = $request->user();

        \assert($user instanceof User);

        abort_unless($conversation->user_id === $user->id, 404);
    }
}
