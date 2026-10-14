<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * S0.4 / S0.5 negative tests (OpenRiC Two-Part Programme Plan V4): keyless,
 * wrong-scope, revoked and expired keys are refused; DELETE needs the delete
 * scope; anonymous writes stay off; CORS opens reads to every origin and
 * writes only to the allow-list; a key can be revoked from the console.
 *
 * Middleware is exercised through stand-in routes, so no RiC tables are needed.
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const ALLOWED = 'https://openric.org';
    private const FOREIGN = 'https://evil.example';

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

        // The read-only POST rule matches the real path, so the stand-in sits there.
        Route::post('api/ric/v1/sparql', fn () => response()->json(['ok' => true]))->middleware('api.cors');

        Route::prefix('api/ric/v1/_t')->middleware(['api.cors'])->group(function () {
            Route::options('{any?}', fn () => response('', 204))->where('any', '.*');
            Route::get('thing', fn () => response()->json(['ok' => true]));
            Route::middleware('api.auth:write')->group(function () {
                Route::post('thing', fn () => response()->json(['ok' => true], 201));
                Route::delete('thing', fn () => response()->json(['ok' => true]));
            });
        });
    }

    private function key(array $scopes, array $extra = []): string
    {
        $raw = 'test-' . bin2hex(random_bytes(8));
        DB::table('ahg_api_key')->insert(array_merge([
            'name' => 'test', 'api_key' => hash('sha256', $raw), 'api_key_prefix' => substr($raw, 0, 8),
            'scopes' => json_encode($scopes), 'is_active' => 1,
        ], $extra));
        return $raw;
    }

    public function test_keyless_writes_are_refused_with_open_write_off(): void
    {
        $this->postJson('/api/ric/v1/_t/thing', [])->assertStatus(401);
        $this->deleteJson('/api/ric/v1/_t/thing')->assertStatus(401);
    }

    public function test_write_scope_alone_cannot_delete(): void
    {
        $k = $this->key(['read', 'write']);
        $this->postJson('/api/ric/v1/_t/thing', [], ['X-API-Key' => $k])->assertStatus(201);
        $this->deleteJson('/api/ric/v1/_t/thing', [], ['X-API-Key' => $k])
            ->assertStatus(403)->assertJsonFragment(['message' => "Scope 'delete' required."]);
    }

    public function test_delete_scope_can_delete(): void
    {
        $k = $this->key(['read', 'write', 'delete']);
        $this->deleteJson('/api/ric/v1/_t/thing', [], ['X-API-Key' => $k])->assertStatus(200);
    }

    public function test_revoked_and_expired_keys_are_refused(): void
    {
        $revoked = $this->key(['read', 'write', 'delete'], ['is_active' => 0]);
        $expired = $this->key(['read', 'write', 'delete'], ['expires_at' => now()->subDay()]);
        $this->postJson('/api/ric/v1/_t/thing', [], ['X-API-Key' => $revoked])->assertStatus(401);
        $this->postJson('/api/ric/v1/_t/thing', [], ['X-API-Key' => $expired])->assertStatus(401);
    }

    public function test_revoke_command_disables_a_key(): void
    {
        $k = $this->key(['read', 'write']);
        $id = DB::table('ahg_api_key')->value('id');
        $this->artisan('openric:revoke-key', ['id' => $id, '--reason' => 'test'])->assertExitCode(0);
        $this->assertSame(0, (int) DB::table('ahg_api_key')->where('id', $id)->value('is_active'));
        $this->postJson('/api/ric/v1/_t/thing', [], ['X-API-Key' => $k])->assertStatus(401);
    }

    public function test_reads_are_open_to_every_origin(): void
    {
        $this->getJson('/api/ric/v1/_t/thing', ['Origin' => self::FOREIGN])
            ->assertHeader('Access-Control-Allow-Origin', '*');
        $this->postJson('/api/ric/v1/sparql', [], ['Origin' => self::FOREIGN])
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_writes_answer_only_allow_listed_origins(): void
    {
        $k = $this->key(['read', 'write']);
        $this->postJson('/api/ric/v1/_t/thing', [], ['X-API-Key' => $k, 'Origin' => self::FOREIGN])
            ->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->postJson('/api/ric/v1/_t/thing', [], ['X-API-Key' => $k, 'Origin' => self::ALLOWED])
            ->assertHeader('Access-Control-Allow-Origin', self::ALLOWED);
    }

    public function test_preflight_for_a_write_from_a_foreign_origin_gets_no_allow_origin(): void
    {
        $preflight = fn (string $origin, string $method) => $this->call('OPTIONS', '/api/ric/v1/_t/thing', [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $method,
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'X-API-Key',
        ]);

        $preflight(self::FOREIGN, 'DELETE')->assertStatus(204)->assertHeaderMissing('Access-Control-Allow-Origin');
        $preflight(self::ALLOWED, 'DELETE')->assertHeader('Access-Control-Allow-Origin', self::ALLOWED);
        $preflight(self::FOREIGN, 'GET')->assertHeader('Access-Control-Allow-Origin', '*');
    }
}
