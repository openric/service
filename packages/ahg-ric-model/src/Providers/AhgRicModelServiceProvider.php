<?php

declare(strict_types=1);

namespace AhgRicModel\Providers;

use AhgRicModel\Services\InheritanceResolver;
use AhgRicModel\Services\OntologyService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AhgRicModelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/ahg-ric-model.php',
            'ahg-ric-model'
        );

        $this->app->singleton(OntologyService::class, function (Application $app): OntologyService {
            $config = $app['config']->get('ahg-ric-model');

            return new OntologyService(
                fusekiUrl:     $config['fuseki']['url'],
                dataset:       $config['fuseki']['dataset'],
                ricoNamespace: $config['rico_namespace'],
                user:          $config['fuseki']['user'],
                password:      $config['fuseki']['password'],
                timeout:       $config['fuseki']['timeout'],
                cacheStore:    $config['cache']['store'],
                cacheTtl:      $config['cache']['ttl'],
                cachePrefix:   $config['cache']['prefix'],
                versions:      $config['versions']['available'],
                latestVersion: $config['versions']['latest'],
                resolver:      new InheritanceResolver(),
            );
        });

        $this->app->singleton(InheritanceResolver::class, fn (): InheritanceResolver => new InheritanceResolver());
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/ahg-ric-model.php' => config_path('ahg-ric-model.php'),
        ], 'ahg-ric-model-config');
    }
}
