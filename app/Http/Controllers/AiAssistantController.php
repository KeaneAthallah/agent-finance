<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Ai\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * AI assistant chat endpoint (spec §19, §20, §47).
 * Read-only by design: the assistant can look up and
 * explain, never write, sync, or approve. Every answer
 * statement carries a trust label (FACT / CALCULATION /
 * AI INTERPRETATION / WARNING).
 */
final class AiAssistantController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ai/assistant');
    }

    public function chat(Request $request, ChatService $chat): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:1', 'max:2000'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        \assert($user instanceof User);

        $answer = $chat->chat(
            $validated['question'],
            $user,
            $validated['history'] ?? [],
        );

        return response()->json($answer->toArray());
    }
}
