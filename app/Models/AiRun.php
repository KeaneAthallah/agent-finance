<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One AI provider call, successful or not (spec §32, §51): the observability
 * record tying a document stage to the prompt/model that produced it.
 *
 * @property int $id
 * @property int|null $document_id
 * @property int|null $prompt_id
 * @property string $stage
 * @property string $provider
 * @property string|null $model
 * @property string|null $prompt_name
 * @property string|null $prompt_version
 * @property string $status
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property int|null $duration_ms
 * @property string|null $error
 * @property array<string, mixed>|null $meta
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Document|null $document
 * @property-read AiPrompt|null $prompt
 */
final class AiRun extends Model
{
    protected $fillable = [
        'document_id',
        'prompt_id',
        'stage',
        'provider',
        'model',
        'prompt_name',
        'prompt_version',
        'status',
        'input_tokens',
        'output_tokens',
        'duration_ms',
        'error',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'duration_ms' => 'integer',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<AiPrompt, $this>
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(AiPrompt::class);
    }
}
