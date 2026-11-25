<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * The /admin/ric pages show drafts and can resync, clear the queue and change
 * orphans. Before 25 November 2026 they required nothing inside the app and were
 * safe only because the web server answers 404 for /admin. They now need a
 * session or an API key with the "admin" scope, which the self-service key
 * request (read, write, delete) never grants.
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminRoutesRequireSignInTest extends TestCase
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

    public function test_every_admin_route_requires_the_admin_scope(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'admin/ric'));

        $this->assertGreaterThan(30, $routes->count());
        foreach ($routes as $route) {
            $this->assertContains('api.auth:admin', $route->gatherMiddleware(), $route->uri());
        }
    }

    public function test_a_caller_without_an_admin_key_is_refused(): void
    {
        $this->getJson('/admin/ric/data?id=1')->assertUnauthorized();
        $this->getJson('/admin/ric/sync-status')->assertUnauthorized();

        // The most a self-service key can hold.
        $k = $this->key(['read', 'write', 'delete']);
        $this->getJson('/admin/ric/data?id=1', ['X-API-Key' => $k])->assertForbidden();
        $this->getJson('/admin/ric/entities/places', ['X-API-Key' => $k])->assertForbidden();
    }

    public function test_an_admin_key_passes_the_check(): void
    {
        Route::middleware(['web', 'api.auth:admin'])->get('/admin/ric/_t', fn () => response()->json(['ok' => true]));

        $this->getJson('/admin/ric/_t', ['X-API-Key' => $this->key(['read', 'admin'])])->assertOk();
    }

    public function test_the_capture_redirect_stays_open(): void
    {
        $this->get('/ric-capture')->assertRedirect('https://capture.openric.org/');
    }
}
