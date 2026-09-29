<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\User;

/**
 * OPD isolation for documents (spec §36). Authorization is always resolved
 * server-side from the user's opd_id — query-string parameters are never
 * trusted.
 */
class DocumentPolicy
{
    /**
     * Anyone authenticated may list documents; scoping is applied in queries.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Users may see documents of their own OPD; platform staff (opd_id null)
     * may see all.
     */
    public function view(User $user, Document $document): bool
    {
        return $user->opd_id === null || $user->opd_id === $document->opd_id;
    }

    /**
     * Upload: admins and verifiers within their OPD context; super admins
     * anywhere.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasRole(UserRole::Verifier);
    }

    /**
     * Delete: OPD admins and super admins, never verifiers/viewers.
     */
    public function delete(User $user, Document $document): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin()
            && $user->opd_id !== null
            && $user->opd_id === $document->opd_id;
    }
}
