<?php

namespace App\Services\Ai;

/**
 * Post-parse grounding verification (roadmap P0-3, §20): every FACT and
 * CALCULATION statement must name a tool that was actually executed in this
 * answer. A "fact" without a matching source is unverifiable — the model may
 * have invented it — so it is downgraded to AI INTERPRETATION rather than
 * presented as truth. WARNING and existing AI INTERPRETATION statements are
 * left untouched.
 */
final class GroundedVerifier
{
    /**
     * @param  array<int, ChatStatement>  $statements
     * @param  array<int, string>  $executedTools
     * @return array<int, ChatStatement>
     */
    public function verify(array $statements, array $executedTools): array
    {
        return array_map(function (ChatStatement $statement) use ($executedTools): ChatStatement {
            if (! in_array($statement->label, [ChatStatement::FACT, ChatStatement::CALCULATION], true)) {
                return $statement;
            }

            $source = $statement->source;

            if ($source === null || ! in_array($source, $executedTools, true)) {
                return new ChatStatement(
                    ChatStatement::AI_INTERPRETATION,
                    $statement->text,
                    $statement->source,
                );
            }

            return $statement;
        }, $statements);
    }
}
