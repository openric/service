<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * StatsController — the admin-gated demand-signal dashboard feed.
 *
 * GET /api/ric/v1/stats?days=30   (Authorization: Bearer <OPENRIC_STATS_TOKEN>)
 *
 * Returns the aggregated openric_usage rollup so the /stats page on openric.org
 * can show what people are looking at, searching for, and which demos they run —
 * the signal for pre-empting enhancements. Gated by a bearer token because the
 * search-query list, while anonymous, is internal intelligence. Reads only.
 */

namespace AhgRic\Http\Controllers;

use App\Http\Controllers\Controller;
use AhgRic\Support\UsageRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $token    = (string) env('OPENRIC_STATS_TOKEN', '');
        $provided = (string) $request->bearerToken();
        if ($token === '' || !hash_equals($token, $provided)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $days  = max(1, min(365, (int) $request->query('days', 30)));
        $since = now()->subDays($days)->toDateString();

        $totals = DB::table('openric_usage')
            ->where('day', '>=', $since)
            ->selectRaw('event_type, SUM(count) AS total')
            ->groupBy('event_type')
            ->pluck('total', 'event_type');

        $byEvent = [];
        foreach (UsageRecorder::EVENTS as $ev) {
            $byEvent[$ev] = (int) ($totals[$ev] ?? 0);
        }

        $questions = DB::table('openric_question')->where('created_at', '>=', $since . ' 00:00:00')->count();

        return response()->json([
            'range_days'        => $days,
            'since'             => $since,
            'totals'            => $byEvent,
            'top_pages'         => $this->top('page_view', $since, 20),
            'top_searches'      => $this->top('search', $since, 25),
            'wizard_started'    => $this->top('wizard_started', $since, 20),
            'wizard_completed'  => $this->top('wizard_completed', $since, 20),
            'ai_suggest_models' => $this->top('ai_suggest', $since, 10),
            'api_actions'       => $this->top('api_action', $since, 20),
            'questions_count'   => $questions,
            'generated_at'      => now()->toIso8601String(),
        ]);
    }

    /** Top labels for one event type within the window: [{label, count}, ...]. */
    private function top(string $event, string $since, int $limit): array
    {
        return DB::table('openric_usage')
            ->where('event_type', $event)
            ->where('day', '>=', $since)
            ->selectRaw('label, SUM(count) AS count')
            ->groupBy('label')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'count' => (int) $r->count])
            ->all();
    }
}
