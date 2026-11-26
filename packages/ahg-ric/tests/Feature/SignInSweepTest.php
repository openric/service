<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * The sign-in sweep of 26 November 2026. Inside the app, the AtoM-compatible
 * /api/v1 and /api groups answered anyone, drafts, donors and accessions
 * included; they were safe on ric.theahg.co.za only because nginx answers 404
 * for them. A read key, meant for published data, could read privacy requests,
 * breaches and the audit log on /api/v2. And a SPARQL SERVICE clause made the
 * store fetch any address the caller named.
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SignInSweepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('ahg_api_key', function ($t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->default(1);
            $t->string('name');
            $t->string('api_key', 64);
            $t->string('api_key_prefix', 16)->nullable();
            $t->text('scopes');
            $t->integer('rate_limit')->nullable();
            $t->dateTime('expires_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->dateTime('last_used_at')->nullable();
            $t->timestamps();
        });
        // /api/v2 counts a key's requests (MySQL-only SQL) before a route's own scope check.
        $this->withoutMiddleware(\AhgApi\Middleware\ApiRateLimit::class);
        config(['ahg-api.open_write.enabled' => false]);
    }

    private function key(array $scopes): string
    {
        $raw = 'test-'.bin2hex(random_bytes(8));
        DB::table('ahg_api_key')->insert([
            'name' => 'test', 'api_key' => hash('sha256', $raw), 'api_key_prefix' => substr($raw, 0, 8),
            'scopes' => json_encode($scopes), 'is_active' => 1,
        ]);

        return $raw;
    }

    public function test_every_legacy_api_route_requires_the_admin_scope(): void
    {
        $legacy = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => (str_starts_with($r->uri(), 'api/v1/') || preg_match('#^api/(records|actor|term|search/io|autocomplete/glam|export-preview|reports/)#', $r->uri()))
            && $r->methods() !== ['OPTIONS']);

        $this->assertGreaterThan(40, $legacy->count());
        foreach ($legacy as $route) {
            $this->assertContains('api.auth:admin', $route->gatherMiddleware(), $route->uri());
        }
        foreach (['api/v2/privacy/dsars', 'api/v2/privacy/dsars/{id}', 'api/v2/privacy/breaches', 'api/v2/audit', 'api/v2/audit/{id}'] as $uri) {
            $route = collect(Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === $uri && in_array('GET', $r->methods(), true));
            $this->assertContains('api.auth:admin', $route->gatherMiddleware(), $uri);
        }
    }

    public function test_a_visitor_or_a_public_key_is_refused_and_a_preflight_still_answers(): void
    {
        foreach (['/api/v1/donors', '/api/v1/accessions', '/api/v1/informationobjects', '/api/records', '/api/reports/pending-counts'] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        $k = $this->key(['read', 'write', 'delete']);
        $this->getJson('/api/v1/donors', ['X-API-Key' => $k])->assertForbidden();
        $this->getJson('/api/v2/privacy/dsars', ['X-API-Key' => $k])->assertForbidden();
        $this->getJson('/api/v2/audit', ['X-API-Key' => $k])->assertForbidden();

        $this->call('OPTIONS', '/api/v1/donors', [], [], [], ['HTTP_ORIGIN' => 'https://example.org', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET'])
            ->assertStatus(204);
    }

    public function test_sparql_refuses_service_clauses_wherever_they_hide(): void
    {
        foreach ([
            'SELECT * WHERE { SERVICE <http://127.0.0.1:9/> { ?s ?p ?o } }',
            'select * where { service <http://10.0.0.1/> { ?s ?p ?o } }',
            // A # inside an IRI is not a comment: the old stripping hid the rest of the line.
            'SELECT * WHERE { ?s a <https://www.ica.org/standards/RiC/ontology#Record> . SERVICE <http://127.0.0.1:9/> { ?s ?p ?o } }',
            // SPARQL has no block comments, so this SERVICE is live syntax.
            'SELECT * WHERE { /* SERVICE <http://127.0.0.1:9/> { ?s ?p ?o } */ }',
        ] as $q) {
            $this->getJson('/api/ric/v1/sparql?query='.rawurlencode($q))->assertForbidden()
                ->assertJsonPath('type', 'https://openric.org/errors/service-not-permitted');
        }

        // The same hiding place defeated the update check.
        $q = 'SELECT * WHERE { ?s a <https://www.ica.org/standards/RiC/ontology#Record> } ; DELETE WHERE { ?s ?p ?o }';
        $this->getJson('/api/ric/v1/sparql?query='.rawurlencode($q))->assertForbidden()
            ->assertJsonPath('type', 'https://openric.org/errors/update-not-permitted');
    }

    public function test_sparql_still_allows_the_words_in_literals_iris_and_comments(): void
    {
        config(['ahg-ric.fuseki_endpoint' => 'http://127.0.0.1:1/none']);
        foreach ([
            'SELECT ?t WHERE { ?s ?p ?t FILTER(CONTAINS(?t, "Service records; delete later")) }',
            "SELECT * WHERE { ?s ?p <https://example.org/service/delete> } # SERVICE and DELETE in a comment",
        ] as $q) {
            // Not refused: it goes to the (absent) store and comes back as a backend failure.
            $r = $this->getJson('/api/ric/v1/sparql?query='.rawurlencode($q))->assertStatus(502);
            $this->assertStringNotContainsString('127.0.0.1', (string) $r->getContent());
        }
    }
}
