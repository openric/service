<?php

declare(strict_types=1);

namespace AhgRicModel\Console\Commands;

use AhgRicModel\Services\OntologyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RebuildCacheCommand extends Command
{
    protected $signature = 'ric-model:rebuild-cache
                            {--model-version= : Only rebuild cache for a specific RiC-CM version}
                            {--all            : Rebuild cache for every available version (default)}';

    protected $description = 'Clear and warm the ahg-ric-model SPARQL cache.';

    public function handle(OntologyService $ontology): int
    {
        $version = $this->option('model-version');
        $store   = $this->cacheStore();
        $prefix  = (string) config('ahg-ric-model.cache.prefix', 'ric_model');

        $versions = $version !== null
            ? [$version]
            : $ontology->listAvailableVersions();

        foreach ($versions as $v) {
            foreach (['entities', 'attributes', 'relations', 'relation-attributes', 'hierarchy'] as $scope) {
                $key = "{$prefix}.{$scope}.{$v}";
                $store->forget($key);
                $this->line("  cleared: {$key}");
            }
        }

        $this->info('');
        $this->info('Warming cache...');
        foreach ($versions as $v) {
            $this->line("  version {$v}");
            $ontology->listEntities($v);
            $ontology->listAttributes($v);
            $ontology->listRelations($v);
            $ontology->listRelationAttributes($v);
            $ontology->getHierarchy($v);
        }

        $this->info('');
        $this->info('Done.');
        return self::SUCCESS;
    }

    private function cacheStore()
    {
        $configured = config('ahg-ric-model.cache.store');
        return $configured !== null && $configured !== ''
            ? Cache::store($configured)
            : Cache::store();
    }
}
