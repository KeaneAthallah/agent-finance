<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A searchable chunk of whitelisted document content with its
 * embedding vector (spec §21, §45). Chunks are only produced
 * from completed extractions — never from raw, unvalidated
 * uploads — so the knowledge base stays whitelisted content.
 *
 * @property int $id
 * @property int $document_id
 * @property int $version
 * @property int $chunk_index
 * @property string $content
 * @property array<int, float>|null $embedding
 * @property int|null $opd_id
 * @property int|null $fiscal_year
 * @property string|null $document_type
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Document $document
 * @property-read Opd|null $opd
 */
final class DocumentChunk extends Model
{
    protected $fillable = [
        'document_id',
        'version',
        'chunk_index',
        'content',
        'embedding',
        'opd_id',
        'fiscal_year',
        'document_type',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'chunk_index' => 'integer',
            'embedding' => 'array',
            'fiscal_year' => 'integer',
        ];
    }

    public $timestamps = true;

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<Opd, $this>
     */
    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }
}
