<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace AhgRic\Http\Controllers;

use Illuminate\Http\Request;

class ExplorerController
{
    /**
     * Show the RIC Explorer page.
     */
    public function index(Request $request)
    {
        // The blade view is provided by the package resources; this controller
        // returns the explorer page which loads the JS assets via Vite.
        return view('ahg-ric::explorer');
    }
}
