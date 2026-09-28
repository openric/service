<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Coverage for the daily demand-signal digest.
 *
 * The behaviour worth pinning is the quiet day: a reference site has many of
 * them, and a notification that fires into an empty day trains the reader to
 * ignore the bell. That is the failure mode this command exists to avoid.
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DailyDigestTest extends TestCase
{
    use RefreshDatabase;

    private string $inbox;
    private string $day;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inbox = sys_get_temp_dir() . '/openric-bell-test-' . bin2hex(random_bytes(4));
        mkdir($this->inbox);

        config(['ahg-ric.workbench.inbox' => $this->inbox]);
        // env() wins over config in the notifier, so make sure it is not set.
        putenv('WORKBENCH_NOTIFICATIONS_INBOX');

        $this->day = now()->subDay()->toDateString();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->inbox . '/*.json') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->inbox);

        parent::tearDown();
    }

    /** @return list<string> */
    private function queued(): array
    {
        return glob($this->inbox . '/*.json') ?: [];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $files = $this->queued();
        $this->assertCount(1, $files, 'exactly one notification should be queued');

        return json_decode((string) file_get_contents($files[0]), true);
    }

    private function seedUsage(string $type, string $label, int $count): void
    {
        DB::table('openric_usage')->insert([
            'day' => $this->day, 'event_type' => $type, 'label' => $label, 'count' => $count,
        ]);
    }

    public function test_a_quiet_day_raises_nothing(): void
    {
        $this->artisan('openric:daily-digest')->assertSuccessful();

        $this->assertSame([], $this->queued(), 'a day with no activity must stay silent');
    }

    public function test_always_overrides_the_quiet_day_rule(): void
    {
        $this->artisan('openric:daily-digest', ['--always' => true])->assertSuccessful();

        $this->assertCount(1, $this->queued());
    }

    public function test_it_summarises_signals_questions_and_the_top_search(): void
    {
        $this->seedUsage('search', 'record set vs record', 9);
        $this->seedUsage('search', 'oai-pmh', 2);
        $this->seedUsage('page_view', '/help/', 30);

        DB::table('openric_question')->insert([
            'body' => 'A question', 'created_at' => $this->day . ' 10:00:00',
        ]);

        $this->artisan('openric:daily-digest')->assertSuccessful();

        $payload = $this->payload();

        $this->assertSame('johan', $payload['username']);
        $this->assertStringContainsString('OpenRiC daily report', $payload['title']);
        $this->assertStringContainsString('Signals recorded: 41', $payload['message']);
        $this->assertStringContainsString('Questions received: 1', $payload['message']);
        // Top search is the highest count, not the first row inserted.
        $this->assertStringContainsString('Most frequent search: record set vs record', $payload['message']);
        $this->assertStringContainsString('Most viewed page: /help/', $payload['message']);
    }

    public function test_a_waiting_question_is_actionable_and_a_quiet_one_is_not(): void
    {
        $this->seedUsage('page_view', '/', 5);
        $this->artisan('openric:daily-digest')->assertSuccessful();
        $this->assertSame('info', $this->payload()['eventType']);

        @unlink($this->queued()[0]);

        DB::table('openric_question')->insert([
            'body' => 'Needs an answer', 'created_at' => $this->day . ' 11:00:00',
        ]);
        $this->artisan('openric:daily-digest')->assertSuccessful();
        $this->assertSame('reminder', $this->payload()['eventType']);
    }

    public function test_it_only_counts_the_requested_day(): void
    {
        $this->seedUsage('page_view', '/', 5);

        DB::table('openric_usage')->insert([
            'day' => now()->toDateString(), 'event_type' => 'page_view', 'label' => '/', 'count' => 999,
        ]);
        DB::table('openric_question')->insert([
            'body' => 'Today, not yesterday', 'created_at' => now()->toDateTimeString(),
        ]);

        $this->artisan('openric:daily-digest', ['--day' => $this->day])->assertSuccessful();

        $message = $this->payload()['message'];
        $this->assertStringContainsString('Signals recorded: 5', $message);
        $this->assertStringContainsString('Questions received: 0', $message);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->seedUsage('page_view', '/', 5);

        $this->artisan('openric:daily-digest', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame([], $this->queued());
    }

    public function test_the_payload_is_renamed_into_place_not_left_dot_prefixed(): void
    {
        $this->seedUsage('page_view', '/', 5);
        $this->artisan('openric:daily-digest')->assertSuccessful();

        // The watcher skips dot-files, so a dot-prefixed leftover is invisible.
        $this->assertSame([], glob($this->inbox . '/.*.json') ?: []);
        $this->assertCount(1, $this->queued());
    }
}
