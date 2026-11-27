<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * /health reports when each scheduled command last succeeded (27 November 2026). A command that has never succeeded
 * is stale, so a scheduler that never starts shows from the first missed day.
 */

namespace Tests\Feature;

use AhgRic\Support\ScheduleLedger;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleLedgerTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->useStoragePath(sys_get_temp_dir().'/openric-ledger-test-'.getmypid());
        $this->file = storage_path('app/openric-schedule/last-success.json');
        @unlink($this->file);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function test_every_scheduled_command_is_listed_and_stale_until_it_succeeds(): void
    {
        $scheduled = $this->getJson('/api/ric/v1/health')->assertOk()->assertJsonPath('status', 'ok')->json('scheduled');
        foreach (['openric:rebuild-graph', 'openric:daily-digest'] as $name) {
            $this->assertArrayHasKey($name, $scheduled);
            $this->assertNull($scheduled[$name]['last_success']);
            $this->assertTrue($scheduled[$name]['stale']);
        }
    }

    public function test_a_successful_run_is_recorded_and_a_failed_one_is_not(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => ScheduleLedger::commandName($e->command) === 'openric:rebuild-graph');
        $this->assertNotNull($event, 'the graph rebuild is scheduled');

        $event->exitCode = 1;
        event(new ScheduledTaskFinished($event, 1.0));
        $this->assertTrue(ScheduleLedger::report()['openric:rebuild-graph']['stale'], 'a failed run is not a success');

        $event->exitCode = 0;
        event(new ScheduledTaskFinished($event, 1.0));
        $this->getJson('/api/ric/v1/health')->assertJsonPath('scheduled.openric:rebuild-graph.stale', false);

        ScheduleLedger::record('openric:daily-digest', time() - (ScheduleLedger::STALE_AFTER_HOURS + 1) * 3600);
        $this->assertTrue(ScheduleLedger::report()['openric:daily-digest']['stale']);
    }

    public function test_the_command_name_is_read_from_the_scheduled_command_line(): void
    {
        $this->assertSame('openric:rebuild-graph', ScheduleLedger::commandName("'/usr/bin/php8.3' 'artisan' openric:rebuild-graph"));
        $this->assertSame('openric:daily-digest', ScheduleLedger::commandName('php artisan openric:daily-digest --quiet'));
        $this->assertNull(ScheduleLedger::commandName(null));
    }
}
