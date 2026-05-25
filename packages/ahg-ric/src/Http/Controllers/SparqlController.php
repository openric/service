<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * SPARQL Access endpoint per openric-spec sparql-access profile v0.1.0:
 *
 *   GET / POST  /api/ric/v1/sparql           → SPARQL 1.1 Query passthrough
 *   GET         /api/ric/v1/sparql/info      → void:Dataset description
 *
 * The endpoint proxies to a Fuseki/Jena backend (configurable via
 * ahg-ric.fuseki_endpoint). Update operations (INSERT / DELETE / CLEAR /
 * LOAD / DROP / CREATE / COPY / MOVE / ADD) are explicitly rejected with
 * 403 + application/problem+json — the spec's sparql-access profile is
 * read-only; a future sparql-update profile would cover writes.
 *
 * Content negotiation honours the Accept header per the SPARQL 1.1
 * Protocol. The proxy passes Accept through to Fuseki and returns
 * Fuseki's response body + Content-Type verbatim, so canonical SPARQL
 * Results JSON / XML / CSV / Turtle / JSON-LD all flow through without
 * mangling.
 *
 * Rate limiting is applied at the route level (throttle:60,1) — 60
 * requests per minute per IP per spec/profiles/sparql-access.md §3.
 *
 * See:
 *   - spec/profiles/sparql-access.md   (the profile this implements)
 *   - routes/api.php                   (where this controller is mounted)
 *   - $openricConformance              (where the profile is declared)
 */

namespace AhgRic\Http\Controllers;

use AhgRic\Support\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SparqlController
{
    /** Max query execution time before we give up on Fuseki. Matches the
     * profile's `max_query_time_seconds: 30` declaration. */
    private const QUERY_TIMEOUT_SECONDS = 30;

    /** SPARQL Update operation keywords. Matched case-insensitively after
     * stripping comments and string literals from the query body so that
     * a SELECT with the literal "DELETE" in a string is not blocked. */
    private const UPDATE_OPS = [
        'INSERT', 'DELETE', 'CLEAR', 'DROP', 'CREATE', 'LOAD', 'COPY', 'MOVE', 'ADD',
    ];

    /**
     * GET|POST /api/ric/v1/sparql
     *
     * The single query entry point. Accepts the query either:
     *   - as ?query= on a GET
     *   - as ?query= or form body on POST (application/x-www-form-urlencoded)
     *   - as the request body on POST with Content-Type: application/sparql-query
     *
     * Returns whatever the backend Fuseki returns (status code,
     * Content-Type, and body all preserved) so SPARQL clients receive
     * canonical responses.
     */
    public function query(Request $request)
    {
        $query = $this->extractQuery($request);
        if ($query === null || $query === '') {
            return ProblemDetails::badRequest('SPARQL query required', [
                'example' => 'SELECT ?s ?p ?o WHERE { ?s ?p ?o } LIMIT 10',
                'hint'    => 'Provide ?query=... on GET, or POST with application/sparql-query body.',
            ]);
        }

        if ($this->isUpdateQuery($query)) {
            return ProblemDetails::build(
                ProblemDetails::TYPE_BASE . 'update-not-permitted',
                'SPARQL Update not permitted',
                403,
                'This endpoint implements the read-only sparql-access profile. '
                . 'INSERT / DELETE / CLEAR / LOAD / DROP / CREATE / COPY / MOVE / ADD operations are rejected. '
                . 'A future sparql-update profile would cover writes.',
                ['profile' => 'sparql-access', 'profile_version' => '0.1.0']
            );
        }

        $accept = (string) $request->header('Accept', 'application/sparql-results+json');

        $backend = $this->forwardToFuseki($query, $accept);

        // Surface Fuseki's status code + Content-Type + body verbatim. This
        // preserves canonical SPARQL Results format and lets clients see
        // backend parse errors (400) as the 4xx they are, instead of
        // being silently wrapped in a misleading 200.
        if ($backend['ok'] === false) {
            // Network / timeout failure talking to Fuseki — surface as 502.
            return ProblemDetails::build(
                ProblemDetails::TYPE_BASE . 'sparql-backend-unavailable',
                'SPARQL Backend Unavailable',
                502,
                'The SPARQL backend did not respond within ' . self::QUERY_TIMEOUT_SECONDS . ' seconds, or returned no response.',
                ['backend_status' => $backend['status']]
            );
        }

        return response($backend['body'], $backend['status'])
            ->header('Content-Type', $backend['content_type'] ?? 'application/sparql-results+json')
            ->header('Vary', 'Accept');
    }

    /**
     * GET /api/ric/v1/sparql/info
     *
     * void:Dataset description for the queryable graph. Emits Turtle by
     * default; honours Accept: application/ld+json. Triple count is
     * computed via a backend SPARQL count, cached for 5 minutes so
     * /sparql/info itself isn't expensive.
     */
    public function info(Request $request)
    {
        $tripleCount = $this->cachedTripleCount();
        $endpointUri = url('/api/ric/v1/sparql');
        $datasetUri  = $endpointUri;
        $title       = config('ahg-ric.dataset_title', 'OpenRiC reference dataset');
        $license     = config('ahg-ric.dataset_license', 'https://creativecommons.org/licenses/by/4.0/');

        $accept = (string) $request->header('Accept', '');
        $wantsJsonLd = str_contains($accept, 'ld+json') || str_contains($accept, 'application/json');

        if ($wantsJsonLd) {
            return response()->json([
                '@context' => [
                    'void'    => 'http://rdfs.org/ns/void#',
                    'dcterms' => 'http://purl.org/dc/terms/',
                    'rico'    => 'https://www.ica.org/standards/RiC/ontology#',
                    'openricx'=> 'https://openric.org/ns/ext/v1#',
                    'skos'    => 'http://www.w3.org/2004/02/skos/core#',
                ],
                '@id'   => $datasetUri,
                '@type' => 'void:Dataset',
                'dcterms:title'         => $title,
                'void:sparqlEndpoint'   => ['@id' => $endpointUri],
                'void:triples'          => $tripleCount,
                'void:vocabulary'       => [
                    ['@id' => 'https://www.ica.org/standards/RiC/ontology#'],
                    ['@id' => 'https://openric.org/ns/ext/v1#'],
                    ['@id' => 'http://www.w3.org/2004/02/skos/core#'],
                ],
                'dcterms:license'       => ['@id' => $license],
            ])->header('Vary', 'Accept');
        }

        // Turtle default — matches the profile's example shape.
        $turtle = "@prefix void: <http://rdfs.org/ns/void#> .\n"
            . "@prefix dcterms: <http://purl.org/dc/terms/> .\n\n"
            . "<{$datasetUri}> a void:Dataset ;\n"
            . "    dcterms:title \"" . addslashes($title) . "\" ;\n"
            . "    void:sparqlEndpoint <{$endpointUri}> ;\n"
            . "    void:triples {$tripleCount} ;\n"
            . "    void:vocabulary <https://www.ica.org/standards/RiC/ontology#> ,\n"
            . "                    <https://openric.org/ns/ext/v1#> ,\n"
            . "                    <http://www.w3.org/2004/02/skos/core#> ;\n"
            . "    dcterms:license <{$license}> .\n";

        return response($turtle, 200)
            ->header('Content-Type', 'text/turtle; charset=utf-8')
            ->header('Vary', 'Accept');
    }

    // -------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------

    /**
     * Pull the query string out of wherever the client put it — query
     * param, form body, or raw application/sparql-query POST body.
     */
    private function extractQuery(Request $request): ?string
    {
        $q = $request->input('query');
        if (is_string($q) && $q !== '') {
            return $q;
        }
        if ($request->isMethod('POST')) {
            $contentType = (string) $request->header('Content-Type', '');
            if (str_contains($contentType, 'application/sparql-query')) {
                $raw = $request->getContent();
                return is_string($raw) && $raw !== '' ? $raw : null;
            }
        }
        return null;
    }

    /**
     * Decide whether the query is a SPARQL Update vs a SPARQL Query.
     * Strips block + line comments and quoted literals, then case-
     * insensitively scans for the nine Update operation keywords.
     */
    private function isUpdateQuery(string $query): bool
    {
        // Remove # line comments and /* ... */ block comments. Note SPARQL
        // doesn't really use block comments but Fuseki tolerates them so
        // be defensive.
        $stripped = preg_replace('!/\*.*?\*/!s', ' ', $query) ?? $query;
        $stripped = preg_replace('/#[^\n]*/m', ' ', $stripped) ?? $stripped;

        // Remove triple-quoted literals first, then single-quoted ones.
        // Order matters: """abc""" must be matched before "abc".
        $stripped = preg_replace('/"""(?:\\\\.|(?!""").)*"""/s', ' ', $stripped) ?? $stripped;
        $stripped = preg_replace("/'''(?:\\\\.|(?!''').)*'''/s", ' ', $stripped) ?? $stripped;
        $stripped = preg_replace('/"(?:\\\\.|[^"\\\\])*"/', ' ', $stripped) ?? $stripped;
        $stripped = preg_replace("/'(?:\\\\.|[^'\\\\])*'/", ' ', $stripped) ?? $stripped;

        $pattern = '/\b(' . implode('|', self::UPDATE_OPS) . ')\b/i';
        return (bool) preg_match($pattern, $stripped);
    }

    /**
     * Forward a query to the configured Fuseki endpoint, passing through
     * the client's Accept header so backend content negotiation does its
     * job. Returns a normalized array — caller decides what to surface.
     */
    private function forwardToFuseki(string $query, string $accept): array
    {
        $endpoint = (string) config('ahg-ric.fuseki_endpoint', 'http://localhost:3030/openric');
        $url = rtrim($endpoint, '/') . '/sparql';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['query' => $query]),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: ' . $accept,
            ],
            CURLOPT_TIMEOUT        => self::QUERY_TIMEOUT_SECONDS,
            CURLOPT_HEADER         => true,
        ]);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            Log::warning('SPARQL backend unreachable', ['url' => $url, 'error' => $err]);
            return ['ok' => false, 'status' => 0, 'body' => '', 'content_type' => null];
        }

        $status      = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        $body = substr((string) $raw, $headerSize);
        return [
            'ok'           => true,
            'status'       => $status,
            'body'         => $body,
            'content_type' => $contentType ?: null,
        ];
    }

    /**
     * Triple count for the void:Dataset description. Cached 5 minutes
     * (the count rarely changes on a write-quiet read-mostly store; if
     * you need fresher numbers, bypass the cache with /sparql).
     */
    private function cachedTripleCount(): int
    {
        return (int) Cache::remember('openric:sparql:triple-count', 300, function () {
            $r = $this->forwardToFuseki(
                'SELECT (COUNT(*) AS ?n) WHERE { ?s ?p ?o }',
                'application/sparql-results+json'
            );
            if (!$r['ok'] || $r['status'] !== 200) {
                return 0;
            }
            $data = json_decode((string) $r['body'], true);
            $value = $data['results']['bindings'][0]['n']['value'] ?? '0';
            return (int) $value;
        });
    }
}
