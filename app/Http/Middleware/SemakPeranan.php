<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hadkan laluan kepada pengguna yang mempunyai sekurang-kurangnya satu
 * daripada peranan yang disenaraikan.
 *
 * Guna: ->middleware('peranan:SUPERADMIN,ADMIN')
 */
class SemakPeranan
{
    public function handle(Request $request, Closure $next, string ...$peranan): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasAnyRole(...$peranan)) {
            abort(403, 'Anda tidak mempunyai kebenaran untuk mengakses halaman ini.');
        }

        return $next($request);
    }
}