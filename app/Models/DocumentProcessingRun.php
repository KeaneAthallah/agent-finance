<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Stage-level processing observability (spec §29, §51). Every pipeline stage
 * writes a run row so the UI shows real progress, never fake progress.
 *
 * @property int $id
 * @property int $document_id
 * @property int $version
 * @property string $stage
 * @property string $status
 * @property string|null $job_id
 * @property string|null $error
 * @property int|null $duration_ms
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class DocumentProcessingRun extends Model
{
    protected $fillable = [
        'document_id',
        'version',
        'stage',
        'status',
        'job_id',
        'error',
        'duration_ms',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
