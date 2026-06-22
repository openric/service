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
    public function stats(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $token    = (string) env('OPENRIC_STATS_TOKEN', '');
        $provided = (string) $request->bearerToken();
        if ($token === '' || !hash_equals($token, $provided)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $days  = max(1, min(365, (int) $request->query('days', 30)));
        $since = now()->subDays($days)->toDateString();

        // CSV export for the "Download" buttons on the /stats dashboard.
        if (strtolower((string) $request->query('format')) === 'csv') {
            return $this->csv($request, $since, $days);
        }

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
            'daily'             => $this->daily($since),
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

    /** A zero-filled per-day series (one row per day in the window, all event counts). */
    private function daily(string $since): array
    {
        $rows = DB::table('openric_usage')
            ->where('day', '>=', $since)
            ->selectRaw('day, event_type, SUM(count) AS c')
            ->groupBy('day', 'event_type')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[substr((string) $r->day, 0, 10)][$r->event_type] = (int) $r->c;
        }

        $out    = [];
        $cursor = \Illuminate\Support\Carbon::parse($since);
        $today  = \Illuminate\Support\Carbon::parse(now()->toDateString());
        while ($cursor->lte($today)) {
            $d   = $cursor->toDateString();
            $row = ['day' => $d];
            foreach (UsageRecorder::EVENTS as $ev) {
                $row[$ev] = (int) ($map[$d][$ev] ?? 0);
            }
            $out[] = $row;
            $cursor->addDay();
        }
        return $out;
    }

    /**
     * Stream a CSV download. ?export=usage (default) dumps the full usage rollup
     * for the window; ?export=questions dumps the submitted questions.
     */
    private function csv(Request $request, string $since, int $days): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $which = strtolower((string) $request->query('export', 'usage')) === 'questions' ? 'questions' : 'usage';
        $name  = "openric-{$which}-last{$days}d.csv";

        if ($which === 'questions') {
            $rows = DB::table('openric_question')->where('created_at', '>=', $since . ' 00:00:00')->orderBy('id')->get();
            $header = ['id', 'created_at', 'contact_email', 'page', 'emailed', 'body'];
            $mapper = fn ($r) => [$r->id, $r->created_at, $r->contact_email, $r->page, $r->emailed, $r->body];
        } else {
            $rows = DB::table('openric_usage')->where('day', '>=', $since)
                ->orderBy('day')->orderBy('event_type')->orderByDesc('count')->get();
            $header = ['day', 'event_type', 'label', 'count'];
            $mapper = fn ($r) => [$r->day, $r->event_type, $r->label, $r->count];
        }

        return response()->streamDownload(function () use ($rows, $header, $mapper) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            foreach ($rows as $r) {
                fputcsv($out, $mapper($r));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
