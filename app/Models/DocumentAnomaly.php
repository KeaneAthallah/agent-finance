<?php

namespace App\Models;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyStatus;
use App\Enums\AnomalyType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A factual anomaly indicator produced by the validation engine (spec §15,
 * §25): always an indicator, never an AI verdict. Humans resolve or dismiss
 * anomalies during review; auto-approval skips documents that still have
 * open anomalies.
 *
 * @property int $id
 * @property int $document_id
 * @property int|null $document_validation_id
 * @property AnomalyType $type
 * @property AnomalySeverity $severity
 * @property AnomalyStatus $status
 * @property string $title
 * @property string|null $detail
 * @property array<string, mixed>|null $evidence
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Document $document
 * @property-read DocumentValidation|null $validation
 * @property-read User|null $resolver
 */
final class DocumentAnomaly extends Model
{
    protected $fillable = [
        'document_id',
        'document_validation_id',
        'type',
        'severity',
        'status',
        'title',
        'detail',
        'evidence',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AnomalyType::class,
            'severity' => AnomalySeverity::class,
            'status' => AnomalyStatus::class,
            'evidence' => 'array',
            'resolved_by' => 'integer',
            'resolved_at' => 'datetime',
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
     * @return BelongsTo<DocumentValidation, $this>
     */
    public function validation(): BelongsTo
    {
        return $this->belongsTo(DocumentValidation::class, 'document_validation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @param  Builder<DocumentAnomaly>  $query
     * @return Builder<DocumentAnomaly>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', AnomalyStatus::Open->value);
    }
}
