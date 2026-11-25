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
 * With the "inputs" option (the graph viewer's /ric-api routes) a request that
 * names a hidden entity by route id, ?id=, ?node= or ?uri= is refused before it runs.
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
     * Reads that carry no entity data. Their ids are dropdown, term or audit-row
     * ids, which can collide with an object id; revisions check their entity in
     * the controller. SPARQL answers from the published-only
     * graph that openric:rebuild-graph builds.
     */
    private const SKIP = '#^api/ric/v1/(vocabulary|relation-types|sparql|openapi\.json|health|conformance|stats)(/|$)|/revisions$#';

    /**
     * Request inputs that name an entity on routes using the "inputs" option: the
     * graph viewer's /ric-api handlers take a record by route id or by ?id=, ?node=
     * or ?uri=, and answer about it without filtering it themselves.
     */
    private const INPUTS = ['id', 'node', 'uri'];

    public function handle(Request $request, Closure $next, string ...$options)
    {
        // The hidden set is cached per request; drop any left by an earlier one
        // in the same process.
        Visibility::forget();

        if (in_array('inputs', $options, true) && in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            $named = array_merge(array_values($request->route()?->parameters() ?? []), array_values($request->only(self::INPUTS)));
            foreach ($named as $value) {
                if (!is_scalar($value)) {
                    continue;
                }
                $value = (string) $value;
                if (ctype_digit($value) ? Visibility::isHidden((int) $value) : Visibility::isHiddenIri($value)) {
                    return ProblemDetails::notFound('Resource not found');
                }
            }
        }

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
