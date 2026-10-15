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
        // Anonymous open-write window, POST creates only (see config/ahg-api.php).
        // Closed on 14 October 2026 because it wrote into a production database;
        // it must only ever be enabled against an isolated demo database.
        if (
            !$request->user()
            && $request->isMethod('post')
            && config('ahg-api.open_write.enabled', false)
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
            return $this->deny($request, 401, 'Unauthorized', 'API key required. Use X-API-Key header or Authorization: Bearer.');
        }

        $hashedKey = hash('sha256', $rawKey);

        $apiKey = DB::table('ahg_api_key')
            ->where('api_key', $hashedKey)
            ->where('is_active', 1)
            ->first();

        if (!$apiKey) {
            return $this->deny($request, 401, 'Unauthorized', 'Invalid or inactive API key.');
        }

        // Check expiry
        if ($apiKey->expires_at && $apiKey->expires_at < now()) {
            return $this->deny($request, 401, 'Unauthorized', 'API key has expired.');
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
            return $this->deny($request, 403, 'Forbidden', 'Open access is limited to entity creation; this endpoint requires an API key.');
        }

        // 2. Payload cap.
        $maxBytes = (int) config('ahg-api.open_write.max_bytes', 65536);
        if ((int) $request->header('Content-Length', 0) > $maxBytes || strlen($request->getContent()) > $maxBytes) {
            return $this->deny($request, 413, 'Payload Too Large', "Open-write requests are capped at {$maxBytes} bytes.");
        }

        // 3. Per-IP daily cap, counted from the inventory (resilient if the
        //    table isn't migrated yet — then the cap is simply not enforced).
        $maxPerDay = (int) config('ahg-api.open_write.max_per_day', 100);
        try {
            $today = DB::table('openric_open_write')
                ->where('ip', $request->ip())
                ->where('created_at', '>=', now()->startOfDay())
                ->count();
            if ($today >= $maxPerDay) {
                return $this->deny($request, 429, 'Too Many Requests', "Daily open-write limit ({$maxPerDay}) reached for this address.");
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
        // Destructive routes name `api.auth:delete` themselves. A blanket
        // "every DELETE needs delete" rule here (v0.17.1) broke legitimate
        // deletes under other scopes, e.g. a user revoking their own key
        // (DELETE /api/v2/keys/{id}, read scope); ApiSecurityTest checks that
        // every RiC and legacy delete route carries api.auth:delete instead.
        if (empty($requiredScopes)) {
            return $next($request);
        }

        $scopes = $request->attributes->get('api_scopes', []);
        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $scopes)) {
                return $this->deny($request, 403, 'Forbidden', "Scope '{$scope}' required.");
            }
        }

        return $next($request);
    }

    /**
     * A refusal. The OpenRiC API answers with an RFC 7807 problem document, as
     * Core Discovery requires; the legacy V1/V2 APIs keep their own shape.
     */
    protected function deny(Request $request, int $status, string $title, string $message)
    {
        if (str_starts_with($request->path(), 'api/ric/v1')) {
            $type = [401 => 'authentication-required', 403 => 'forbidden', 413 => 'payload-too-large']
                [$status] ?? strtolower(str_replace(' ', '-', $title));
            return response()->json([
                'type'     => 'https://openric.org/errors/' . $type,
                'title'    => $title,
                'status'   => $status,
                'detail'   => $message,
                'instance' => '/' . $request->path(),
            ], $status, ['Content-Type' => 'application/problem+json']);
        }

        return response()->json(['success' => false, 'error' => $title, 'message' => $message], $status);
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
