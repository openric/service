<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Daily demand-signal digest, raised in the Workbench bell.
 *
 * Summarises one day of openric_usage and openric_question. Deliberately NOT a
 * WhatsApp message: a daily analytics summary reads to Meta's classifier as a
 * business newsletter, which is the MARKETING pattern, and a MARKETING template
 * is gated on a consent grant the maintainer's number does not have. The estate
 * has evidence for this - lifefile_review_reminder classified MARKETING for
 * wording no more promotional than a plain review reminder.
 *
 * The bell has none of those constraints, and is the surface the standing rules
 * already nominate for "tell Johan something later".
 *
 * Usage:
 *   php artisan openric:daily-digest                 # yesterday
 *   php artisan openric:daily-digest --day=2026-09-27
 *   php artisan openric:daily-digest --dry-run       # print, do not notify
 */

namespace AhgRic\Console\Commands;

use AhgRic\Support\WorkbenchNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DailyDigest extends Command
{
    protected $signature = 'openric:daily-digest
        {--day= : The day to summarise, YYYY-MM-DD. Defaults to yesterday.}
        {--dry-run : Print the digest; do not raise a notification.}
        {--always : Notify even when there was no activity at all.}';

    protected $description = 'Summarise a day of OpenRiC demand signals into the Workbench bell.';

    public function handle(): int
    {
        if (! Schema::hasTable('openric_usage') || ! Schema::hasTable('openric_question')) {
            $this->warn('Usage tables are not present; nothing to summarise.');

            return self::SUCCESS;
        }

        $day = $this->option('day')
            ? Carbon::parse((string) $this->option('day'))->toDateString()
            : now()->subDay()->toDateString();

        $signals = (int) DB::table('openric_usage')->where('day', $day)->sum('count');

        $questions = (int) DB::table('openric_question')
            ->whereBetween('created_at', [$day . ' 00:00:00', $day . ' 23:59:59'])
            ->count();

        $topSearch = DB::table('openric_usage')
            ->where('day', $day)
            ->where('event_type', 'search')
            ->orderByDesc('count')
            ->value('label');

        $topPage = DB::table('openric_usage')
            ->where('day', $day)
            ->where('event_type', 'page_view')
            ->orderByDesc('count')
            ->value('label');

        // A silent day is the normal case for a reference site. Notifying on it
        // daily trains the reader to ignore the bell, which costs more than the
        // missing information. --always overrides for a deliberate check.
        if ($signals === 0 && $questions === 0 && ! $this->option('always')) {
            $this->info("No activity on {$day}; no notification raised.");

            return self::SUCCESS;
        }

        $lines = [
            "Signals recorded: {$signals}",
            'Questions received: ' . $questions,
        ];

        if (filled($topSearch)) {
            $lines[] = 'Most frequent search: ' . $topSearch;
        }
        if (filled($topPage)) {
            $lines[] = 'Most viewed page: ' . $topPage;
        }

        $message = implode("\n", $lines);
        $title = 'OpenRiC daily report - ' . Carbon::parse($day)->format('j M Y');

        $this->line($title);
        $this->line($message);

        if ($this->option('dry-run')) {
            $this->comment('Dry run; no notification raised.');

            return self::SUCCESS;
        }

        $raised = WorkbenchNotifier::notify(
            $title,
            $message,
            rtrim((string) config('app.url'), '/') . '/stats',
            // A question waiting for an answer is actionable; a quiet day is not.
            $questions > 0 ? 'reminder' : 'info',
        );

        if (! $raised) {
            $this->error('Notification was not queued; see the log.');

            return self::FAILURE;
        }

        $this->info('Digest raised in the Workbench bell.');

        return self::SUCCESS;
    }
}
