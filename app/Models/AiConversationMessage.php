<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One turn in an AI conversation: the user question or the assistant's
 * labeled answer (roadmap P0-1). statements/tool_calls are the parsed answer
 * shape so the UI can re-render exactly what was served.
 *
 * @property int $id
 * @property int $conversation_id
 * @property int|null $ai_run_id
 * @property string $role
 * @property string $content
 * @property array<int, array{label: string, text: string, source: string|null}>|null $statements
 * @property array<int, array{name: string, arguments: array<string, mixed>}>|null $tool_calls
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read AiConversation $conversation
 */
final class AiConversationMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'ai_run_id',
        'role',
        'content',
        'statements',
        'tool_calls',
    ];

    protected function casts(): array
    {
        return [
            'statements' => 'array',
            'tool_calls' => 'array',
        ];
    }

    /**
     * @return BelongsTo<AiConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    /**
     * @return BelongsTo<AiRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }
}
