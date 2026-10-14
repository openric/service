<?php

namespace AhgApi\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Reads (GET/HEAD) are open to every origin: this is a public open-data API.
 * Write methods are answered for the configured origins only
 * (config/ahg-api.php, cors.write_origins). A browser on any other site gets
 * no Access-Control-Allow-Origin for a write, so it will not send one.
 *
 * This narrows what web pages can do from a visitor's browser. It is not
 * authorisation: server-side callers ignore CORS, and the API-key scope check
 * in ApiAuthenticate is what actually decides.
 */
class ApiCors
{
    private const ALLOW_HEADERS = 'Content-Type, X-API-Key, X-REST-API-Key, Authorization, Accept';
    private const READ_METHODS = ['GET', 'HEAD'];

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('OPTIONS')) {
            $wanted = strtoupper((string) $request->header('Access-Control-Request-Method', 'GET'));
            $response = response('', 204)->header('Access-Control-Max-Age', 86400);
            return $this->apply($request, $response, $wanted);
        }

        return $this->apply($request, $next($request), $request->getMethod());
    }

    // POST endpoints that only read: a SPARQL query (SPARQL 1.1 Protocol allows
    // POST for queries; updates are refused server-side) and SHACL validation of
    // a submitted document. Browser clients on any site may use them.
    private function isReadOnlyPost(Request $request): bool
    {
        return (bool) preg_match('#/api/ric/v1/(sparql|validate)$#', '/' . $request->path());
    }

    private function apply(Request $request, $response, string $method)
    {
        if (in_array($method, self::READ_METHODS, true) || ($method === 'POST' && $this->isReadOnlyPost($request))) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', $method === 'POST' ? 'GET, HEAD, POST, OPTIONS' : 'GET, HEAD, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', self::ALLOW_HEADERS);
            return $response;
        }

        // Writes: echo the origin back only if it is allowed, and say the answer varies by origin.
        $response->headers->set('Vary', 'Origin', false);
        $origin = (string) $request->header('Origin', '');
        if ($origin !== '' && in_array($origin, config('ahg-api.cors.write_origins', []), true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, HEAD, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', self::ALLOW_HEADERS);
        }
        return $response;
    }
}
