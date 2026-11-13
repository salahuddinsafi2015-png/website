<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API clients must never be redirected to a login page.
 *
 * Without an "Accept: application/json" header Laravel's unauthenticated
 * handling redirects to a named login route, which does not exist for this
 * token-based API and would surface as a 500. Setting the header makes every
 * /api/* request render JSON (401, 403, 422) instead.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*')) {
            $request->headers->set('Accept', 'application/json');
        }

        return $next($request);
    }
}
