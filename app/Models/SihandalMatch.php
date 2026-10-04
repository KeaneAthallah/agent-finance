<?php

namespace App\Models;

use App\Enums\SihandalMatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A SIHANDAL match candidate (spec §17): a suggestion from the
 * matching job, ordered by confidence, decided by a human.
 *
 * @property int $id
 * @property int $document_id
 * @property int $version
 * @property string $entity_type
 * @property string $entity_id
 * @property array<string, mixed> $sihandal_data
 * @property int $confidence
 * @property SihandalMatchStatus $status
 * @property int|null $confirmed_by
 * @property Carbon|null $confirmed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Document $document
 * @property-read User|null $confirmedBy
 */
class SihandalMatch extends Model
{
    protected $fillable = [
        'document_id',
        'version',
        'entity_type',
        'entity_id',
        'sihandal_data',
        'confidence',
        'status',
        'confirmed_by',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'status' => SihandalMatchStatus::class,
            'sihandal_data' => 'array',
            'confirmed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
