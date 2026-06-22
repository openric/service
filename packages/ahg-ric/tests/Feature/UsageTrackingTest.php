<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Feature coverage for the anonymous demand-signal tracker: /track beacon,
 * /ask contact form, and the admin-gated /stats feed.
 */

namespace Tests\Feature;

use AhgRic\Support\UsageRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UsageTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_track_increments_the_daily_rollup(): void
    {
        $this->postJson('/api/ric/v1/track', ['event' => 'page_view', 'label' => '/help/sparql/'])
            ->assertStatus(202)->assertJson(['ok' => true]);
        $this->postJson('/api/ric/v1/track', ['event' => 'page_view', 'label' => '/help/sparql/'])
            ->assertStatus(202);

        $row = DB::table('openric_usage')->where('event_type', 'page_view')->where('label', '/help/sparql/')->first();
        $this->assertNotNull($row);
        $this->assertSame(2, (int) $row->count);
        // One rollup row only — no per-visitor rows.
        $this->assertSame(1, DB::table('openric_usage')->count());
    }

    public function test_track_rejects_server_only_and_unknown_events(): void
    {
        // The browser beacon may not fake server-side counters.
        $this->postJson('/api/ric/v1/track', ['event' => 'ai_suggest', 'label' => 'qwen3:8b'])->assertStatus(422);
        $this->postJson('/api/ric/v1/track', ['event' => 'api_action', 'label' => 'records'])->assertStatus(422);
        $this->postJson('/api/ric/v1/track', ['event' => 'nonsense', 'label' => 'x'])->assertStatus(422);
        $this->assertSame(0, DB::table('openric_usage')->count());
    }

    public function test_search_queries_are_captured_as_labels(): void
    {
        $this->postJson('/api/ric/v1/track', ['event' => 'search', 'label' => 'sparql endpoint'])->assertStatus(202);
        $this->assertDatabaseHas('openric_usage', ['event_type' => 'search', 'label' => 'sparql endpoint', 'count' => 1]);
    }

    public function test_ask_stores_question_and_sends_mail(): void
    {
        Mail::fake();
        $this->postJson('/api/ric/v1/ask', [
            'body'  => 'Does the API support OAI-PMH selective harvesting?',
            'email' => 'asker@example.org',
            'page'  => '/help/exporting-and-harvesting/',
        ])->assertStatus(200)->assertJson(['ok' => true]);

        $this->assertDatabaseHas('openric_question', [
            'contact_email' => 'asker@example.org',
            'page'          => '/help/exporting-and-harvesting/',
            'emailed'       => true,
        ]);
    }

    public function test_ask_honeypot_silently_drops_bots(): void
    {
        $this->postJson('/api/ric/v1/ask', ['body' => 'buy cheap stuff now', 'website' => 'http://spam.example'])
            ->assertStatus(200);
        $this->assertSame(0, DB::table('openric_question')->count());
    }

    public function test_ask_requires_a_real_question(): void
    {
        $this->postJson('/api/ric/v1/ask', ['body' => 'hi'])->assertStatus(422);
    }

    public function test_stats_requires_the_admin_token(): void
    {
        // Override every channel env() may read so a real .env token can't leak in.
        putenv('OPENRIC_STATS_TOKEN=secrettoken123');
        $_ENV['OPENRIC_STATS_TOKEN'] = 'secrettoken123';
        $_SERVER['OPENRIC_STATS_TOKEN'] = 'secrettoken123';

        $this->getJson('/api/ric/v1/stats')->assertStatus(401);
        $this->getJson('/api/ric/v1/stats', ['Authorization' => 'Bearer wrong'])->assertStatus(401);

        UsageRecorder::bump('page_view', '/help/');
        UsageRecorder::bump('search', 'sparql');

        $this->getJson('/api/ric/v1/stats?days=30', ['Authorization' => 'Bearer secrettoken123'])
            ->assertStatus(200)
            ->assertJsonStructure(['totals', 'top_pages', 'top_searches', 'questions_count', 'daily' => [['day', 'page_view', 'search']]])
            ->assertJsonPath('totals.page_view', 1)
            ->assertJsonPath('totals.search', 1);

        putenv('OPENRIC_STATS_TOKEN');
        unset($_ENV['OPENRIC_STATS_TOKEN'], $_SERVER['OPENRIC_STATS_TOKEN']);
    }

    public function test_stats_csv_export_streams_rows(): void
    {
        putenv('OPENRIC_STATS_TOKEN=secrettoken123');
        $_ENV['OPENRIC_STATS_TOKEN'] = 'secrettoken123';
        $_SERVER['OPENRIC_STATS_TOKEN'] = 'secrettoken123';

        UsageRecorder::bump('page_view', '/help/sparql/');

        $res = $this->get('/api/ric/v1/stats?days=30&format=csv&export=usage', ['Authorization' => 'Bearer secrettoken123']);
        $res->assertStatus(200);
        $this->assertStringContainsString('text/csv', $res->headers->get('content-type'));
        $body = $res->streamedContent();
        $this->assertStringContainsString('day,event_type,label,count', $body);
        $this->assertStringContainsString('/help/sparql/', $body);

        putenv('OPENRIC_STATS_TOKEN');
        unset($_ENV['OPENRIC_STATS_TOKEN'], $_SERVER['OPENRIC_STATS_TOKEN']);
    }

    public function test_recorder_bumps_server_side_events(): void
    {
        UsageRecorder::bump('ai_suggest', 'qwen3:8b');
        UsageRecorder::bump('ai_suggest', 'qwen3:8b');
        UsageRecorder::bump('api_action', 'records');
        $this->assertDatabaseHas('openric_usage', ['event_type' => 'ai_suggest', 'label' => 'qwen3:8b', 'count' => 2]);
        $this->assertDatabaseHas('openric_usage', ['event_type' => 'api_action', 'label' => 'records', 'count' => 1]);
    }
}
