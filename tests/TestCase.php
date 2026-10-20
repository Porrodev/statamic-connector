<?php

namespace Porro\Statamic\Tests;

use Inertia\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Porro\Laravel\PorroServiceProvider;
use Porro\Statamic\StatamicConnectorServiceProvider;
use Statamic\Providers\StatamicServiceProvider;
use Statamic\Statamic;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return array_values(array_filter([
            StatamicServiceProvider::class,
            // Statamic 6's control panel is built on Inertia.
            class_exists(ServiceProvider::class) ? ServiceProvider::class : null,
            PorroServiceProvider::class,
            StatamicConnectorServiceProvider::class,
        ]));
    }

    protected function getPackageAliases($app): array
    {
        return ['Statamic' => Statamic::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('porro.url', 'https://porro.test');
        $app['config']->set('statamic.users.repository', 'file');
        $app['config']->set('statamic.stache.watcher', false);
        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('statamic.static_caching.strategy', 'half');
        $app['config']->set('statamic.git.enabled', false);
    }
}
