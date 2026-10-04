<?php

namespace App\Models;

use App\Enums\SihandalSyncStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A sync attempt to SIHANDAL (spec §48): the full request/response
 * history of every write to the system of record.
 *
 * @property int $id
 * @property int $document_id
 * @property int|null $sihandal_match_id
 * @property int|null $user_id
 * @property string $endpoint
 * @property string $method
 * @property array<string, mixed> $request
 * @property array<string, mixed>|null $response
 * @property int|null $http_status
 * @property SihandalSyncStatus $status
 * @property string|null $error
 * @property int $attempts
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Document $document
 * @property-read SihandalMatch|null $match
 * @property-read User|null $user
 */
class SihandalSync extends Model
{
    protected $fillable = [
        'document_id',
        'sihandal_match_id',
        'user_id',
        'endpoint',
        'method',
        'request',
        'response',
        'http_status',
        'status',
        'error',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'request' => 'array',
            'response' => 'array',
            'http_status' => 'integer',
            'attempts' => 'integer',
            'status' => SihandalSyncStatus::class,
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
     * @return BelongsTo<SihandalMatch, $this>
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(SihandalMatch::class, 'sihandal_match_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
