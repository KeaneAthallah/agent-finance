<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single extracted field with its confidence and evidence (spec §12, §13):
 * value, 0–100 confidence, and where in the document it came from (page,
 * optional bbox, source text) so reviewers can jump to the location.
 *
 * @property int $id
 * @property int $document_extraction_id
 * @property string $field
 * @property string|null $value
 * @property int|null $confidence
 * @property array{page?: int, bbox?: array{0: float, 1: float, 2: float, 3: float}, source_text?: string}|null $evidence
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read DocumentExtraction $extraction
 */
final class DocumentExtractionField extends Model
{
    protected $fillable = [
        'document_extraction_id',
        'field',
        'value',
        'confidence',
        'evidence',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'evidence' => 'array',
        ];
    }

    /**
     * @return BelongsTo<DocumentExtraction, $this>
     */
    public function extraction(): BelongsTo
    {
        return $this->belongsTo(DocumentExtraction::class);
    }
}
