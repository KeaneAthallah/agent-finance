<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One structured-extraction pass over a document (spec §10, §38): keyed by
 * processing version so re-processing never overwrites history.
 *
 * @property int $id
 * @property int $document_id
 * @property int|null $ai_run_id
 * @property int $version
 * @property string $schema_name
 * @property string $schema_version
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int|null $confidence
 * @property string|null $error
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Document $document
 * @property-read AiRun|null $run
 * @property-read HasMany<DocumentExtractionField, $this> $fields
 */
final class DocumentExtraction extends Model
{
    protected $fillable = [
        'document_id',
        'ai_run_id',
        'version',
        'schema_name',
        'schema_version',
        'payload',
        'status',
        'confidence',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'payload' => 'array',
            'confidence' => 'integer',
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
     * @return BelongsTo<AiRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }

    /**
     * Per-field values, confidence and evidence.
     *
     * @return HasMany<DocumentExtractionField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(DocumentExtractionField::class);
    }

    /**
     * Latest completed extraction for a document.
     *
     * @param  Builder<DocumentExtraction>  $query
     */
    public function scopeLatestCompleted(Builder $query): void
    {
        $query->where('status', 'completed')
            ->orderByDesc('version');
    }
}
