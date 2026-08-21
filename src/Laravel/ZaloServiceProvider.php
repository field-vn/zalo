<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Laravel;

use FieldVn\Zalo\Contracts\BotRepository;
use FieldVn\Zalo\Contracts\Factory;
use FieldVn\Zalo\Contracts\OaRepository;
use FieldVn\Zalo\Contracts\Transport;
use FieldVn\Zalo\Core\Http\GuzzleTransport;
use FieldVn\Zalo\Laravel\Console\AuthorizeCommand;
use FieldVn\Zalo\Laravel\Console\DoctorCommand;
use FieldVn\Zalo\Laravel\Console\InstallCommand;
use FieldVn\Zalo\Laravel\Console\OaAddCommand;
use FieldVn\Zalo\Laravel\Console\OaListCommand;
use FieldVn\Zalo\Laravel\Console\OaTestCommand;
use FieldVn\Zalo\Laravel\Console\RefreshTokensCommand;
use FieldVn\Zalo\Laravel\Console\StatusCommand;
use FieldVn\Zalo\Laravel\Http\Middleware\Authorize;
use FieldVn\Zalo\Laravel\Managers\ZaloManager;
use FieldVn\Zalo\Laravel\Repositories\EloquentBotRepository;
use FieldVn\Zalo\Laravel\Repositories\EloquentOaRepository;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ZaloServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/zalo.php', 'zalo');

        // Bind theo interface — người dùng typehint Contracts, không phải class cụ thể.
        $this->app->singleton(Transport::class, function (): Transport {
            /** @var array{timeout:int, connect_timeout:int, retry:array{times:int, sleep:int, on:list<int>}} $http */
            $http = config('zalo.http');

            return new GuzzleTransport(
                retry: $http['retry'],
                timeout: (float) $http['timeout'],
                connectTimeout: (float) $http['connect_timeout'],
            );
        });

        $this->app->singleton(OaRepository::class, EloquentOaRepository::class);
        $this->app->singleton(BotRepository::class, EloquentBotRepository::class);

        $this->app->singleton(Factory::class, fn ($app): ZaloManager => new ZaloManager(
            $app,
            $app->make(OaRepository::class),
            $app->make(BotRepository::class),
        ));

        // Alias để inject được ZaloManager trực tiếp (command cần các method
        // Laravel-specific như tokenProviderFor() vốn không thuộc Factory).
        $this->app->alias(Factory::class, ZaloManager::class);
        $this->app->alias(Factory::class, 'zalo');
    }

    public function boot(): void
    {
        // Migration nằm trong package, tự chạy ở lần `php artisan migrate` kế tiếp.
        // Composer KHÔNG thể tự migrate lúc install — chỉ script của root package
        // mới được chạy, nên đây là cách "tự động" đúng đắn nhất Laravel cho phép.
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->registerRoutes();
        $this->registerScheduler();

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();

            $this->commands([
                InstallCommand::class,
                DoctorCommand::class,
                StatusCommand::class,
                RefreshTokensCommand::class,
                AuthorizeCommand::class,
                OaAddCommand::class,
                OaListCommand::class,
                OaTestCommand::class,
            ]);
        }
    }

    protected function registerRoutes(): void
    {
        $this->registerUiRoutes();
        $this->registerWebhookRoutes();
    }

    protected function registerUiRoutes(): void
    {
        if (! config('zalo.ui.enabled', true)) {
            return;
        }

        /** @var list<string> $middleware */
        $middleware = (array) config('zalo.ui.middleware', ['web']);

        Route::group([
            'prefix' => (string) config('zalo.ui.path', 'zalo'),
            // Authorize luôn được nối vào cuối — người dùng đổi `ui.middleware`
            // không được phép vô tình gỡ mất lớp bảo vệ.
            'middleware' => [...$middleware, Authorize::class],
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../../routes/ui.php');
        });
    }

    protected function registerWebhookRoutes(): void
    {
        if (! config('zalo.webhook.enabled', true)) {
            return;
        }

        // Không middleware: Zalo gọi vào, không có session và không có CSRF
        // token. Chữ ký X-ZEvent-Signature là lớp bảo vệ duy nhất và đủ.
        Route::group([
            'prefix' => (string) config('zalo.webhook.path', 'zalo/webhook'),
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../../routes/webhook.php');
        });
    }

    protected function registerScheduler(): void
    {
        if (! config('zalo.scheduler.enabled', true)) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('zalo:token:refresh --all')
                ->hourly()
                ->withoutOverlapping()
                ->runInBackground();
        });
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../../config/zalo.php' => config_path('zalo.php'),
        ], 'zalo-config');

        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'zalo-migrations');
    }

    /** @return list<string> */
    public function provides(): array
    {
        return [Factory::class, Transport::class, OaRepository::class, BotRepository::class, 'zalo'];
    }
}
