<?php

namespace App\Models;

use App\Enums\ReviewDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One human review decision on a document (spec §14): approve, reject, or
 * request re-analysis. Every decision snapshots the extraction version it
 * judged so history survives re-processing (spec §38).
 *
 * @property int $id
 * @property int $document_id
 * @property int|null $reviewer_id
 * @property ReviewDecision $decision
 * @property string|null $notes
 * @property int|null $extraction_version
 * @property array<string, mixed>|null $extraction_snapshot
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Document $document
 * @property-read User|null $reviewer
 */
final class DocumentReview extends Model
{
    protected $fillable = [
        'document_id',
        'reviewer_id',
        'decision',
        'notes',
        'extraction_version',
        'extraction_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'decision' => ReviewDecision::class,
            'extraction_version' => 'integer',
            'extraction_snapshot' => 'array',
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
     * The human who made the decision; null when the reviewer account was
     * later deleted (the review row itself is kept for the audit trail).
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
