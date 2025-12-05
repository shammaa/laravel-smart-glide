<?php

declare(strict_types=1);

namespace Shammaa\SmartGlide\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Shammaa\SmartGlide\SmartGlideServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('cache.default', 'array');
    }

    protected function getPackageProviders($app): array
    {
        return [
            SmartGlideServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
    }
}

