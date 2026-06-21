<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * UsageController — the public, anonymous demand-signal beacon.
 *
 * POST /api/ric/v1/track  { "event": "page_view", "label": "/help/sparql/" }
 *
 * Stores nothing that identifies a person — it only bumps a daily rollup counter
 * (see UsageRecorder + the migration). Public and throttled; client events the
 * site beacon cannot fake server-side (ai_suggest, api_action) are rejected here
 * so those counts can only come from the real server-side hooks.
 */

namespace AhgRic\Http\Controllers;

use App\Http\Controllers\Controller;
use AhgRic\Support\UsageRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UsageController extends Controller
{
    /** Events the browser beacon is allowed to report. */
    private const CLIENT_EVENTS = ['page_view', 'search', 'wizard_started', 'wizard_completed'];

    public function track(Request $request): JsonResponse
    {
        $event = (string) $request->input('event', '');
        $label = (string) $request->input('label', '');

        if (!in_array($event, self::CLIENT_EVENTS, true)) {
            return response()->json(['error' => 'bad_event'], 422);
        }
        if (mb_strlen($label) > 300) {
            $label = mb_substr($label, 0, 300);
        }

        UsageRecorder::bump($event, $label);

        return response()->json(['ok' => true], 202);
    }
}
