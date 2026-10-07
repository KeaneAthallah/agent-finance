<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A user's assistant conversation (roadmap P0-1): a titled thread of labeled
 * messages. History lives here so the chat context is rebuilt server-side,
 * never trusted from the client.
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property Carbon $updated_at
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read Collection<int, AiConversationMessage> $messages
 */
final class AiConversation extends Model
{
    protected $fillable = [
        'user_id',
        'title',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AiConversationMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AiConversationMessage::class, 'conversation_id');
    }
}
