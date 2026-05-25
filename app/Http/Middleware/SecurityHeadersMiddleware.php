<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Force HTTPS in production
        if (app()->environment('production') && !$request->isSecure()) {
            $secureUrl = preg_replace('/^http:/i', 'https:', $request->fullUrl());
            return redirect()->secure($secureUrl);
        }

        $response = $next($request);

        // Apply security headers
        $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer-when-downgrade');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=()');

        return $response;
    }
}
