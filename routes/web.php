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
| These resolve to the API record (JSON-LD) for machine clients. A browser
| (an Accept header that includes text/html) is sent to the record page in the
| OpenRiC Graph Explorer, which opens the entity's graph with its details
| showing. Nothing redirects off the OpenRiC services: the record page used to
| be Heratio's (OPENRIC_FRONTEND_URL), which made an OpenRiC identifier's
| behaviour depend on a separate product and sent Turtle clients to HTML.
|
| OPENRIC_RECORD_PAGE overrides the record page; it is given the entity path
| (e.g. /informationobject/egyptian-boat) appended to it.
*/

$recordPage = (string) env('OPENRIC_RECORD_PAGE', 'https://viewer.openric.org/?start=');

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

foreach ($pluralMap as $segment => $plural) {
    Route::get("/{$segment}/{key}", function (string $key) use ($segment, $plural, $recordPage) {
        $accept = (string) request()->header('Accept', '');
        $browser = str_contains($accept, 'text/html') && !str_contains($accept, 'application/ld+json');

        return $browser
            ? redirect($recordPage . "/{$segment}/{$key}", 303)
            : redirect('/api/ric/v1/' . $plural . '/' . $key, 303);
    })->where('key', '[A-Za-z0-9\-_]+');
}
