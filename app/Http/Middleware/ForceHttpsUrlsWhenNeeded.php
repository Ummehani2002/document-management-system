<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures route()/url() use https when the site is served over HTTPS.
 * Otherwise Chrome may block downloads as "Insecure download" (HTTPS page + HTTP file URL).
 */
class ForceHttpsUrlsWhenNeeded
{
    public function handle(Request $request, Closure $next): Response
    {
        $appUrl = config('app.url');
        if (is_string($appUrl) && str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        } elseif ($request->secure() || $request->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
        }

        return $next($request);
    }
}
