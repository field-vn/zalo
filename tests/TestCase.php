<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Tests;

use FieldVn\Zalo\Laravel\Managers\ZaloManager;
use FieldVn\Zalo\Laravel\ZaloServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Gate là static — không dọn thì test này rò rỉ sang test khác.
        ZaloManager::clearAuthGate();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function tearDown(): void
    {
        // Test nào đổi environment sang `production` phải được khôi phục TRƯỚC
        // khi testbench chạy migrate:rollback lúc huỷ app. Nếu không,
        // ConfirmableTrait thấy môi trường production sẽ hỏi xác nhận, và
        // OutputStyle đã bị mock nên ném BadMethodCallException — lỗi trông
        // hoàn toàn không liên quan tới thứ đang test.
        if ($this->app !== null) {
            $this->app->detectEnvironment(fn (): string => 'testing');
        }

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [ZaloServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        /** @var Application $app */
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('zalo.apps.default.app_id', 'test-app-id');
        $app['config']->set('zalo.apps.default.app_secret', 'test-app-secret');
    }
}
