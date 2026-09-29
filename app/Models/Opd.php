<?php

namespace App\Models;

use Database\Factories\OpdFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 */
class Opd extends Model
{
    /** @use HasFactory<OpdFactory> */
    use HasFactory;

    protected $table = 'opds';

    protected $fillable = ['code', 'name', 'is_active'];

    /**
     * Users belonging to this OPD.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
