<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
 * @property Carbon|null $validated_at
 * @property Carbon|null $synced_at
 * @property Carbon $created_at
 * @property Carbon $updated_at * @property-read Opd $opd
 * @property-read DocumentType|null $documentType
 * @property-read User $uploader
 * @property-read Collection<int, DocumentReview> $reviews
 * @property-read Collection<int, DocumentValidation> $validations
 * @property-read Collection<int, DocumentAnomaly> $anomalies
 * @property-read Collection<int, SihandalMatch> $matches
 * @property-read Collection<int, SihandalSync> $syncs
 * @property-read Collection<int, DocumentChunk> $chunks
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
        'validated_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'file_size' => 'integer',
            'page_count' => 'integer',
            'fiscal_year' => 'integer',
            'metadata' => 'array',
            'validated_at' => 'datetime',
            'synced_at' => 'datetime',
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
     * Structured extraction passes across all processing versions (§38).
     *
     * @return HasMany<DocumentExtraction, $this>
     */
    public function extractions(): HasMany
    {
        return $this->hasMany(DocumentExtraction::class);
    }

    /**
     * Human review decisions on this document (§14).
     *
     * @return HasMany<DocumentReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(DocumentReview::class);
    }

    /**
     * Validation engine runs (§15).
     *
     * @return HasMany<DocumentValidation, $this>
     */
    public function validations(): HasMany
    {
        return $this->hasMany(DocumentValidation::class);
    }

    /**
     * Anomaly indicators across all validation passes (§15, §25).
     *
     * @return HasMany<DocumentAnomaly, $this>
     */
    public function anomalies(): HasMany
    {
        return $this->hasMany(DocumentAnomaly::class);
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
     * SIHANDAL match candidates across all processing versions (§17).
     *
     * @return HasMany<SihandalMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(SihandalMatch::class);
    }

    /**
     * SIHANDAL sync attempts with full request/response history (§48).
     *
     * @return HasMany<SihandalSync, $this>
     */
    public function syncs(): HasMany
    {
        return $this->hasMany(SihandalSync::class);
    }

    /**
     * RAG chunks of whitelisted content with embeddings (§21, §45).
     *
     * @return HasMany<DocumentChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    /**
     * Scope to the OPDs a user may access (spec §36). Users with an OPD see
     * only their OPD; platform users (null opd_id) see all. The scope is
     * applied server-side — client parameters are never trusted.
     *
     * @param  Builder<Document>  $query
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
