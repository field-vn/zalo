<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel;

use Illuminate\Support\ServiceProvider;

class ZaloServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/zalo.php', 'zalo');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/zalo.php' => config_path('zalo.php'),
            ], 'zalo-config');
        }
    }
}
