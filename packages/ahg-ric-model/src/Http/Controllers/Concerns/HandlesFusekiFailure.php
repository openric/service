<?php

declare(strict_types=1);

namespace AhgRicModel\Http\Controllers\Concerns;

use Closure;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Graceful degradation when Fuseki is unreachable. Controllers wrap their
 * OntologyService calls with `$this->guard(fn () => …)`; on failure we log
 * once and render the `ahg-ric-model::not-configured` partial with context.
 *
 * Any thrown RuntimeException from OntologyService (connection refused,
 * malformed SPARQL response, etc.) is caught and rendered as 503. HTTP
 * exceptions (like NotFoundHttpException from a missing entity ID) MUST
 * bubble so the framework returns the correct status — they're control
 * flow, not infrastructure errors.
 */
trait HandlesFusekiFailure
{
    /**
     * @template T
     * @param Closure(): T $operation
     * @return T|\Illuminate\Contracts\View\View
     */
    protected function guard(Closure $operation)
    {
        try {
            return $operation();
        } catch (HttpException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            Log::warning('ahg-ric-model: Fuseki operation failed — rendering not-configured view.', [
                'message' => $e->getMessage(),
            ]);
            return response()->view('ahg-ric-model::not-configured', [
                'reason'  => $e->getMessage(),
                'fuseki'  => config('ahg-ric-model.fuseki'),
            ], 503);
        }
    }
}
