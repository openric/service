<?php

declare(strict_types=1);

namespace AhgRicModel\Http\Middleware;

use AhgRicModel\Services\OntologyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Rejects requests whose `{version}` route parameter is not one of the
 * versions the bundled ontology data covers. Keeps bookmarks stable:
 * citing `/reference/ric-cm/1.0/entities/RiC-E05` continues to work as long
 * as v1.0 is in listAvailableVersions(); unknown versions 404 loudly
 * instead of silently mis-serving.
 */
class EnsureVersionExists
{
    public function __construct(private readonly OntologyService $ontology)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $version = $request->route('version');
        if ($version !== null && !in_array($version, $this->ontology->listAvailableVersions(), true)) {
            throw new NotFoundHttpException("RiC-CM version '{$version}' is not available.");
        }

        return $next($request);
    }
}
