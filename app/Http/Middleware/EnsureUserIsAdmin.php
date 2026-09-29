<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the Administration area (spec §57). Usage: ->middleware('role:admin')
 * — accepts any role slug, e.g. role:admin,role:verifier.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if ($roles === []) {
            if (! $user->isAdmin()) {
                abort(403, 'This area requires an administrator role.');
            }

            return $next($request);
        }

        // Super admins bypass role gates (spec §36 authorization model).
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        foreach ($roles as $role) {
            $enumRole = UserRole::tryFrom($role);

            if ($enumRole !== null && $user->hasRole($enumRole)) {
                return $next($request);
            }
        }

        abort(403, 'This area requires one of the following roles: '.implode(', ', $roles).'.');
    }
}
