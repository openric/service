<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * When each scheduled command last succeeded, for /health (27 November 2026). From 28 September to 27 November 2026
 * the scheduler never started and neither the graph rebuild nor the daily digest ran, with nothing to show it. Every
 * command in the schedule is listed, so one that has never succeeded shows as stale from its first missed day.
 */

namespace AhgRic\Support;

use Illuminate\Console\Scheduling\Schedule;

class ScheduleLedger
{
    /** Every scheduled command runs daily; one that has not succeeded for this long is stale. */
    public const STALE_AFTER_HOURS = 30;

    private static function file(): string
    {
        return storage_path('app/openric-schedule/last-success.json');
    }

    /** The artisan command a scheduled task runs, such as openric:rebuild-graph, or null for a closure or shell job. */
    public static function commandName(?string $command): ?string
    {
        return $command && preg_match("/artisan'?\\s+'?([a-z0-9-]+:[a-z0-9:-]+)/i", $command, $m) ? $m[1] : null;
    }

    public static function record(string $name, ?int $at = null): void
    {
        $file = self::file();
        @mkdir(dirname($file), 0775, true);
        $all = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        $all[$name] = date('c', $at ?? time());
        file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /** @return array<string, array{last_success: ?string, stale: bool}> every scheduled command, by name */
    public static function report(): array
    {
        $file = self::file();
        $all = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        $names = [];
        foreach (app(Schedule::class)->events() as $event) {
            if ($name = self::commandName($event->command)) {
                $names[$name] = true;
            }
        }
        $out = [];
        foreach (array_keys($names + $all) as $name) {
            $at = $all[$name] ?? null;
            $out[$name] = ['last_success' => $at, 'stale' => $at === null || strtotime($at) < time() - self::STALE_AFTER_HOURS * 3600];
        }
        ksort($out);

        return $out;
    }
}
