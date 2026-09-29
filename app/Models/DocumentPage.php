<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-page text/OCR state for a document (spec §9).
 *
 * @property int $id
 * @property int $document_id
 * @property int $page_number
 * @property string|null $image_path
 * @property string|null $extracted_text
 * @property int|null $ocr_confidence
 * @property string|null $text_source
 * @property string|null $language
 * @property string $processing_status
 * @property string|null $error
 */
class DocumentPage extends Model
{
    protected $fillable = [
        'document_id',
        'page_number',
        'image_path',
        'extracted_text',
        'ocr_confidence',
        'text_source',
        'language',
        'processing_status',
        'error',
    ];

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
