<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Versioned AI prompt (spec §33): active production prompts are immutable —
 * a change creates a new version row. Extraction and classification jobs read
 * the active version by name.
 *
 * @property int $id
 * @property string $name
 * @property string $version
 * @property string $system_prompt
 * @property string|null $user_prompt
 * @property array<string, mixed>|null $schema
 * @property string|null $model
 * @property string $temperature
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read HasMany<AiRun, $this> $runs
 */
final class AiPrompt extends Model
{
    protected $fillable = [
        'name',
        'version',
        'system_prompt',
        'user_prompt',
        'schema',
        'model',
        'temperature',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'temperature' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<AiRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(AiRun::class);
    }

    /**
     * Highest active version of a named prompt, falling back to the latest
     * inactive version so jobs degrade visibly instead of fatally.
     *
     * @param  Builder<AiPrompt>  $query
     */
    public function scopeLatestVersion(Builder $query, string $name): ?self
    {
        /** @var static|null */
        return self::query()
            ->where('name', $name)
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->first();
    }
}
