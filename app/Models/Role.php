<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 */
class Role extends Model
{
    protected $fillable = ['slug', 'name', 'description'];

    /**
     * Users having this role.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Resolve a role by slug (UserRole enum value), or null.
     */
    public static function findSlug(string $slug): ?self
    {
        return static::query()->where('slug', $slug)->first();
    }
}
