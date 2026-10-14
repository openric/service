<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * API authentication and CORS settings. Read through config() so the values
 * survive `php artisan config:cache`; a bare env() call in middleware returns
 * null once config is cached.
 */

return [

    // Anonymous entity creation. Off since 14 October 2026: it wrote into a
    // production database. Turn it on only against an isolated demo database.
    'open_write' => [
        'enabled'     => filter_var(env('OPENRIC_OPEN_WRITE', false), FILTER_VALIDATE_BOOLEAN),
        'max_bytes'   => (int) env('OPENRIC_OPEN_WRITE_MAX_BYTES', 65536),
        'max_per_day' => (int) env('OPENRIC_OPEN_WRITE_MAX_PER_DAY', 100),
    ],

    // Browser origins allowed to make write requests (POST/PUT/PATCH/DELETE)
    // cross-origin. Reads stay open to every origin. CORS only governs what a
    // browser lets a page do; authorisation is the API-key scope check.
    'cors' => [
        'write_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'OPENRIC_CORS_WRITE_ORIGINS',
            'https://openric.org,https://viewer.openric.org,https://capture.openric.org,https://ric.theahg.co.za'
        ))))),
    ],

];
