<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One run of the deterministic validation engine over an extraction (spec
 * §15): the pass/fail outcome plus a summary; per-rule findings land on
 * document_anomalies.
 *
 * @property int $id
 * @property int $document_id
 * @property int|null $document_extraction_id
 * @property int $version
 * @property string $engine_version
 * @property string $result
 * @property array<string, mixed>|null $summary
 * @property int|null $duration_ms
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Document $document
 * @property-read DocumentExtraction|null $extraction
 * @property-read HasMany<DocumentAnomaly, $this> $anomalies
 */
final class DocumentValidation extends Model
{
    protected $fillable = [
        'document_id',
        'document_extraction_id',
        'version',
        'engine_version',
        'result',
        'summary',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'duration_ms' => 'integer',
            'summary' => 'array',
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
     * @return BelongsTo<DocumentExtraction, $this>
     */
    public function extraction(): BelongsTo
    {
        return $this->belongsTo(DocumentExtraction::class, 'document_extraction_id');
    }

    /**
     * Per-rule findings emitted by this validation pass.
     *
     * @return HasMany<DocumentAnomaly, $this>
     */
    public function anomalies(): HasMany
    {
        return $this->hasMany(DocumentAnomaly::class);
    }
}
