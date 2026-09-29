<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Token/cost usage for a single AI call (spec §32, §56): feeds the cost
 * dashboard in Phase 9 and enforces cost awareness per extraction.
 *
 * @property int $id
 * @property int $ai_run_id
 * @property string $provider
 * @property string|null $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property string|null $cost_estimate
 * @property int|null $duration_ms
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read AiRun $run
 */
final class AiUsage extends Model
{
    protected $table = 'ai_usage';

    protected $fillable = [
        'ai_run_id',
        'provider',
        'model',
        'input_tokens',
        'output_tokens',
        'cost_estimate',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AiRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }
}
