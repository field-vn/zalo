<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Tests;

use FieldVn\Zalo\Laravel\ZaloServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ZaloServiceProvider::class];
    }
}
