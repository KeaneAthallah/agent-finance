<?php

namespace App\Services\Ai;

/**
 * Providers capable of streaming chat completion deltas (roadmap P1-1).
 * chatStream() accumulates the exact same result contract as chat(), but
 * pushes each content fragment through $onDelta the moment it arrives.
 * Providers that do not implement this interface silently fall back to the
 * non-streaming chat() path in ChatService.
 */
interface StreamingAIProviderInterface
{
    /**
     * @param  callable(string $delta): void  $onDelta  Called once per content fragment.
     */
    public function chatStream(AiChatRequest $request, callable $onDelta): AiChatResult;
}
