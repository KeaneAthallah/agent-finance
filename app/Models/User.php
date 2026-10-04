<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property int|null $role_id
 * @property int|null $opd_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Role|null $role
 * @property-read Opd|null $opd
 */
#[Fillable(['name', 'email', 'password', 'role_id', 'opd_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The role assigned to this user.
     *
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * The OPD (work unit) this user belongs to. Null means cross-OPD access
     * (platform staff); OPD isolation is enforced server-side via policies.
     *
     * @return BelongsTo<Opd, $this>
     */
    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    /**
     * Whether the user has any of the given roles.
     */
    public function hasRole(UserRole ...$roles): bool
    {
        $slug = $this->role?->slug;

        if ($slug === null) {
            return false;
        }

        foreach ($roles as $role) {
            if ($role->value === $slug) {
                return true;
            }
        }

        return false;
    }

    /**
     * Platform super administrator.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(UserRole::SuperAdmin);
    }

    /**
     * Administrator (super admins included).
     */
    public function isAdmin(): bool
    {
        return $this->isSuperAdmin() || $this->hasRole(UserRole::Admin);
    }
}
