<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * UsageRecorder — increment an anonymous daily demand-signal counter.
 *
 * This is the ONLY writer of openric_usage. It stores nothing that can identify
 * a person: a single row per (day, event_type, label) carrying a count. Recording
 * is best-effort and wrapped in try/catch — a counter failure must never break a
 * request. See the migration for the privacy rationale.
 */

namespace AhgRic\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UsageRecorder
{
    /** Only these event types are ever stored; anything else is dropped. */
    public const EVENTS = [
        'page_view', 'search', 'wizard_started', 'wizard_completed', 'ai_suggest', 'api_action',
    ];

    private const LABEL_MAX = 200;

    /**
     * Increment the counter for (today, $type, $label) by one.
     *
     * Portable across MySQL/Postgres/sqlite: try an atomic increment, and only
     * insert when the row does not yet exist (re-incrementing on the insert race).
     */
    public static function bump(string $type, ?string $label = ''): void
    {
        try {
            if (!in_array($type, self::EVENTS, true)) {
                return;
            }
            if (!Schema::hasTable('openric_usage')) {
                return;
            }

            $label = self::normaliseLabel($label);
            $day   = now()->toDateString();
            $key   = ['day' => $day, 'event_type' => $type, 'label' => $label];

            if (DB::table('openric_usage')->where($key)->increment('count') > 0) {
                return;
            }

            try {
                DB::table('openric_usage')->insert($key + ['count' => 1]);
            } catch (\Throwable $e) {
                // Lost the insert race — the row now exists, so just increment.
                DB::table('openric_usage')->where($key)->increment('count');
            }
        } catch (\Throwable $e) {
            // Counters are best-effort; never surface a failure to the caller.
        }
    }

    /** Trim, collapse whitespace, lower-case-ish nothing, and cap length. */
    private static function normaliseLabel(?string $label): string
    {
        $label = trim((string) $label);
        $label = preg_replace('/\s+/u', ' ', $label) ?? '';
        if ($label === '') {
            $label = '(none)';
        }
        return mb_substr($label, 0, self::LABEL_MAX);
    }
}
