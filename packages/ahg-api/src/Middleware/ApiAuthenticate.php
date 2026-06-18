<?php

namespace AhgApi\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiAuthenticate
{
    /**
     * Authenticate via API key (header) or Laravel session.
     * Sets request attributes: api_key_id, api_user_id, api_scopes.
     */
    public function handle(Request $request, Closure $next, string ...$requiredScopes)
    {
        // TEMPORARY open-write window ("free for all to use"). When
        // OPENRIC_OPEN_WRITE=true, unauthenticated CREATE (POST) requests pass
        // through with read+write scope, so public tools (e.g. the openric.org
        // modelling wizard) can create entities without an API key. Deliberately
        // scoped to POST only — PUT/PATCH/DELETE still require a key, so nobody
        // can edit or destroy existing data anonymously. Rate limiting and
        // request logging still apply. Set the env to false (and config:clear)
        // to "close it up" again — no code change needed.
        if (
            !$request->user()
            && $request->isMethod('post')
            && filter_var(env('OPENRIC_OPEN_WRITE', false), FILTER_VALIDATE_BOOLEAN)
        ) {
            return $this->openWrite($request, $next, $requiredScopes);
        }

        // Try session auth first (logged-in admin = full scopes)
        if ($request->user()) {
            $request->attributes->set('api_key_id', null);
            $request->attributes->set('api_user_id', $request->user()->id ?? null);
            $request->attributes->set('api_scopes', ['read', 'write', 'delete', 'batch', 'publish:write']);
            return $this->checkScopes($request, $next, $requiredScopes);
        }

        // Try API key from headers
        $rawKey = $request->header('X-API-Key')
            ?? $request->header('X-REST-API-Key')
            ?? $this->bearerToken($request);

        if (!$rawKey) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'API key required. Use X-API-Key header or Authorization: Bearer.',
            ], 401);
        }

        $hashedKey = hash('sha256', $rawKey);

        $apiKey = DB::table('ahg_api_key')
            ->where('api_key', $hashedKey)
            ->where('is_active', 1)
            ->first();

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'Invalid or inactive API key.',
            ], 401);
        }

        // Check expiry
        if ($apiKey->expires_at && $apiKey->expires_at < now()) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'API key has expired.',
            ], 401);
        }

        // Update last_used_at
        DB::table('ahg_api_key')
            ->where('id', $apiKey->id)
            ->update(['last_used_at' => now()]);

        $scopes = json_decode($apiKey->scopes, true) ?: [];

        $request->attributes->set('api_key_id', $apiKey->id);
        $request->attributes->set('api_user_id', $apiKey->user_id);
        $request->attributes->set('api_scopes', $scopes);

        return $this->checkScopes($request, $next, $requiredScopes);
    }

    /**
     * Hardened open-write path (OPENRIC_OPEN_WRITE=true). Anonymous POST to the
     * safe entity-creation endpoints ONLY — never /import or /upload — with a
     * payload cap, a per-IP daily cap, minimal scope (read+write, no
     * batch/publish/delete), and an inventory row per creation so the whole
     * window can be torn down with `php artisan openric:purge-open-write`.
     */
    protected function openWrite(Request $request, Closure $next, array $requiredScopes)
    {
        // 1. Allowlist: entity-creation endpoints only. Blocks /import, /upload, etc.
        if (!preg_match('#(^|/)api/ric/v1/(records|record-parts|record-sets|agents|repositories|functions|places|rules|activities|instantiations|relations)$#', $request->path())) {
            return response()->json([
                'success' => false, 'error' => 'Forbidden',
                'message' => 'Open access is limited to entity creation; this endpoint requires an API key.',
            ], 403);
        }

        // 2. Payload cap.
        $maxBytes = (int) env('OPENRIC_OPEN_WRITE_MAX_BYTES', 65536);
        if ((int) $request->header('Content-Length', 0) > $maxBytes || strlen($request->getContent()) > $maxBytes) {
            return response()->json([
                'success' => false, 'error' => 'Payload Too Large',
                'message' => "Open-write requests are capped at {$maxBytes} bytes.",
            ], 413);
        }

        // 3. Per-IP daily cap, counted from the inventory (resilient if the
        //    table isn't migrated yet — then the cap is simply not enforced).
        $maxPerDay = (int) env('OPENRIC_OPEN_WRITE_MAX_PER_DAY', 100);
        try {
            $today = DB::table('openric_open_write')
                ->where('ip', $request->ip())
                ->where('created_at', '>=', now()->startOfDay())
                ->count();
            if ($today >= $maxPerDay) {
                return response()->json([
                    'success' => false, 'error' => 'Too Many Requests',
                    'message' => "Daily open-write limit ({$maxPerDay}) reached for this address.",
                ], 429);
            }
        } catch (\Throwable $e) { /* table missing → skip the cap */ }

        // 4. Grant minimal scope and mark the request.
        $request->attributes->set('api_key_id', null);
        $request->attributes->set('api_user_id', null);
        $request->attributes->set('api_scopes', ['read', 'write']);
        $request->attributes->set('open_write', true);

        $response = $this->checkScopes($request, $next, $requiredScopes);

        // 5. Inventory the created entity for teardown.
        try {
            if ($response->getStatusCode() === 201) {
                $body = json_decode($response->getContent(), true);
                if (!empty($body['id'])) {
                    DB::table('openric_open_write')->insert([
                        'entity_id'   => (int) $body['id'],
                        'entity_type' => substr(strrchr('/' . $request->path(), '/'), 1),
                        'ip'          => $request->ip(),
                        'created_at'  => now(),
                    ]);
                }
            }
        } catch (\Throwable $e) { /* inventory failure must not break the response */ }

        return $response;
    }

    protected function checkScopes(Request $request, Closure $next, array $requiredScopes)
    {
        if (empty($requiredScopes)) {
            return $next($request);
        }

        $scopes = $request->attributes->get('api_scopes', []);
        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $scopes)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Forbidden',
                    'message' => "Scope '{$scope}' required.",
                ], 403);
            }
        }

        return $next($request);
    }

    protected function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }
}
