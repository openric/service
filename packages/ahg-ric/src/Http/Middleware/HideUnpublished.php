<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Scrubs every JSON read of the public API with Support\Visibility: a response
 * about a hidden entity becomes 404, and nested references to one are removed.
 * The controllers already exclude hidden entities from their queries; this is
 * the net under the serializers, which embed related records in many shapes.
 */

namespace AhgRic\Http\Middleware;

use AhgRic\Support\ProblemDetails;
use AhgRic\Support\Visibility;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HideUnpublished
{
    /**
     * Reads that carry no entity data. Their ids are dropdown or term ids, which
     * can collide with an object id. SPARQL answers from the published-only
     * graph that openric:rebuild-graph builds.
     */
    private const SKIP = '#^api/ric/v1/(vocabulary|relation-types|sparql|openapi\.json|health|conformance|stats)(/|$)#';

    public function handle(Request $request, Closure $next)
    {
        // The hidden set is cached per request; drop any left by an earlier one
        // in the same process.
        Visibility::forget();
        $response = $next($request);

        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)
            || !$response instanceof JsonResponse
            || !$response->isSuccessful()
            || preg_match(self::SKIP, $request->path())) {
            return $response;
        }

        $data = $response->getData(true);
        if (!is_array($data)) {
            return $response;
        }
        $clean = Visibility::scrub($data);
        if ($clean === null) {
            return ProblemDetails::notFound('Resource not found');
        }
        return $clean === $data ? $response : $response->setData($clean);
    }
}
