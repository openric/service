<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Human-facing URL redirects
|--------------------------------------------------------------------------
|
| Entity @ids in the Viewing API look like
|   https://<host>/informationobject/<slug>
|   https://<host>/actor/<slug>
|   https://<host>/place/<id>          etc.
|
| These resolve to JSON-LD for machine clients via /api/ric/v1/<plural>/…,
| but when a human clicks the link in a browser they expect an HTML page.
| The reference API service is headless — no HTML renderer — so we redirect
| to whatever host does have a human UI (Heratio, in our case).
|
| Configured via OPENRIC_FRONTEND_URL in .env. If unset, these routes
| 404 rather than redirect into a void.
*/

$frontendUrl = rtrim((string) env('OPENRIC_FRONTEND_URL', ''), '/');

// Entity path → backing platform path. Map of our canonical URL segments
// to what the Heratio UI uses (they happen to match for informationobject,
// actor, repository; the RiC-native types don't have UI pages there yet
// so they route to the API JSON-LD instead).
$redirectMap = [
    'informationobject' => 'informationobject',
    'actor'             => 'actor',
    'repository'        => 'repository',
    'function'          => null,  // no heratio UI; fall through to API
    'place'             => null,
    'activity'          => null,
    'rule'              => null,
    'instantiation'     => null,
];

foreach ($redirectMap as $segment => $heratioSegment) {
    Route::get("/{$segment}/{key}", function (string $key) use ($segment, $heratioSegment, $frontendUrl) {
        // Content negotiation: JSON-accepting clients always get the API URL.
        $wantsJson = request()->wantsJson()
            || str_contains((string) request()->header('Accept', ''), 'application/ld+json');

        if ($wantsJson || !$heratioSegment || !$frontendUrl) {
            // Route to the API endpoint. For numeric-id segments we map
            // to the appropriate plural; for slug-based (records / agents
            // / repositories) we use the records endpoint's slug form.
            $pluralMap = [
                'informationobject' => 'records',
                'actor'             => 'agents',
                'repository'        => 'repositories',
                'function'          => 'functions',
                'place'             => 'places',
                'activity'          => 'activities',
                'rule'              => 'rules',
                'instantiation'     => 'instantiations',
            ];
            return redirect('/api/ric/v1/' . $pluralMap[$segment] . '/' . $key, 302);
        }

        // Human-facing redirect to the configured frontend.
        return redirect("{$frontendUrl}/{$heratioSegment}/{$key}", 302);
    })->where('key', '[A-Za-z0-9\-_]+');
}
