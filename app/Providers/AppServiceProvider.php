<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prepend security headers middleware so it runs for every request.
        // Use the HTTP kernel to ensure the middleware is registered early.
        $kernel = $this->app->make(Kernel::class);
        $kernel->prependMiddleware(\App\Http\Middleware\SecurityHeadersMiddleware::class);
    }
}
