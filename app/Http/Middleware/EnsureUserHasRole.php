<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi akses route berdasarkan role user.
 *
 * Pemakaian: ->middleware('role:admin') atau ->middleware('role:admin,viewer')
 *
 * @see dokumentasi/04-autentikasi.md §4
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null
                && $user->role !== null
                && in_array($user->role->value, $roles, true),
            403
        );

        return $next($request);
    }
}
