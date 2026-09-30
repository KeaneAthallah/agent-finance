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

    /**
     * Review actions (approve/reject/request re-analysis): verifiers and
     * admins within their OPD; super admins across OPDs (spec §14, §36).
     * Viewers can never review. Called with a class name for a general
     * ability check and with a document for the per-record check.
     */
    public function review(User $user, ?Document $document = null): bool
    {
        if ($document !== null && ! $this->view($user, $document)) {
            return false;
        }

        return $user->isSuperAdmin()
            || $user->isAdmin()
            || $user->hasRole(UserRole::Verifier);
    }
}
