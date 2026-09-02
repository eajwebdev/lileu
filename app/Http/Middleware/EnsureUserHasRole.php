<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Gate a route to one or more roles: ->middleware('role:admin,cashier').
     * Admins are implicitly allowed everywhere a cashier is.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if(! $user || ! $user->is_active, 403, 'Your account is not active.');

        if ($user->isAdmin()) {
            return $next($request);
        }

        abort_unless(in_array($user->role, $roles, true), 403, 'You do not have access to this area.');

        return $next($request);
    }
}
