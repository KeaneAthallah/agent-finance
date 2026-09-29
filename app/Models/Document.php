<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An uploaded financial document (spec §7, §8). SIHANDAL remains the
 * financial system of record; this model only describes the file and its
 * processing lifecycle.
 *
 * @property int $id
 * @property int $opd_id
 * @property int|null $document_type_id
 * @property int $uploaded_by
 * @property DocumentStatus $status
 * @property string $original_filename
 * @property string $file_path
 * @property string $mime_type
 * @property int $file_size
 * @property string $file_hash
 * @property int|null $page_count
 * @property int|null $fiscal_year
 * @property string|null $document_number
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Opd $opd
 * @property-read DocumentType|null $documentType
 * @property-read User $uploader
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'opd_id',
        'document_type_id',
        'uploaded_by',
        'status',
        'original_filename',
        'file_path',
        'mime_type',
        'file_size',
        'file_hash',
        'page_count',
        'fiscal_year',
        'document_number',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'file_size' => 'integer',
            'page_count' => 'integer',
            'fiscal_year' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * The OPD (work unit) this document belongs to — the tenancy boundary.
     *
     * @return BelongsTo<Opd, $this>
     */
    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    /**
     * Per-page extracted text / OCR state.
     *
     * @return HasMany<DocumentPage, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(DocumentPage::class);
    }

    /**
     * Processing runs across all versions.
     *
     * @return HasMany<DocumentProcessingRun, $this>
     */
    public function processingRuns(): HasMany
    {
        return $this->hasMany(DocumentProcessingRun::class);
    }

    /**
     * @return BelongsTo<DocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope to the OPDs a user may access (spec §36). Users with an OPD see
     * only their OPD; platform users (null opd_id) see all. The scope is
     * applied server-side — client parameters are never trusted.
     *
     * @param  Builder<Document>  $query
     * @param  User  $user
     * @return Builder<Document>
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->opd_id === null) {
            return $query;
        }

        return $query->where('opd_id', $user->opd_id);
    }

    /**
     * Human-readable file size.
     */
    public function humanFileSize(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
