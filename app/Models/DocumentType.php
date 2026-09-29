<?php

namespace App\Models;

use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Extensible document classification catalog (spec §6). The AI classifier is
 * seeded with these types but new types can be added without code changes.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property array<string, mixed>|null $schema
 * @property string $version
 * @property bool $is_active
 */
class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'description', 'schema', 'version', 'is_active'];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Active types, ordered for display.
     *
     * @return Collection<int, static>
     */
    public static function active(): Collection
    {
        return static::query()->where('is_active', true)->orderBy('name')->get();
    }
}
