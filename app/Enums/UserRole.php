<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Verifier = 'verifier';
    case Viewer = 'viewer';

    /**
     * @return array<string>
     */
    public static function slugs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
