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
        // X-Frame-Options, X-Content-Type-Options and Referrer-Policy are set
        // once, by nginx, on every response; setting them here too sent each
        // twice, and Referrer-Policy with two conflicting values.
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=()');

        return $response;
    }
}
