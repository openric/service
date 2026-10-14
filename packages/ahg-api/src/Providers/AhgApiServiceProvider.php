<?php

namespace AhgApi\Providers;

use AhgApi\Middleware\ApiAuthenticate;
use AhgApi\Middleware\ApiCors;
use AhgApi\Middleware\ApiLogger;
use AhgApi\Middleware\ApiRateLimit;
use AhgApi\Services\ApiKeyService;
use AhgApi\Services\WebhookService;
use Illuminate\Support\ServiceProvider;

class AhgApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/ahg-api.php', 'ahg-api');

        // ApiCors owns CORS for every api/* route group (reads open, writes
        // allow-listed). Take api/* out of Laravel's global HandleCors, whose
        // default config allows every origin and method and would otherwise
        // answer preflights itself and re-add a wildcard to every response.
        config(['cors.paths' => array_values(array_diff((array) config('cors.paths', []), ['api/*']))]);
        $this->app->singleton(ApiKeyService::class);
        $this->app->singleton(WebhookService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');

        // Register API middleware aliases
        $router = $this->app['router'];
        $router->aliasMiddleware('api.auth', ApiAuthenticate::class);
        $router->aliasMiddleware('api.ratelimit', ApiRateLimit::class);
        $router->aliasMiddleware('api.log', ApiLogger::class);
        $router->aliasMiddleware('api.cors', ApiCors::class);
    }
}
