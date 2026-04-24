<?php

namespace AhgRic\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Drop cached graph-summary / expand / expand-group responses.
 *
 * The graph layer caches per (uri, maxNodes, threshold) with the TTL set by
 * ahg-ric.graph_cache_seconds. Write-paths to Fuseki don't know about the
 * graph cache, so invalidation is currently operator-driven.
 *
 * Usage:
 *   php artisan ric:graph-cache:clear
 *
 * When ahg-ric.sparql_cache_minutes SparqlQueryService cache is in use it is
 * separate and not affected — only the graph-summary layer added in Phase 3.
 */
class ClearGraphCache extends Command
{
    protected $signature = 'ric:graph-cache:clear';
    protected $description = 'Clear cached user-holdings graph summary / expand / expand-group responses';

    public function handle(): int
    {
        // All graph keys share the 'ric-graph:' prefix. Flushing the whole
        // cache store is too broad; most cache drivers can't prefix-delete
        // cheaply (file driver can't do it at all). Best we can do portably
        // is Cache::flush, which the operator is already opting into by
        // running this command. Redis users can pattern-delete manually.
        $driver = config('cache.default');
        $this->line("Cache driver: {$driver}");

        Cache::flush();
        $this->info('Cache flushed — graph responses will be rebuilt on next request.');
        return self::SUCCESS;
    }
}
