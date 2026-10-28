<?php

namespace Porro\Statamic\Tests;

use Facades\Statamic\Version;
use Inertia\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Porro\Core\CrontabIo;
use Porro\Core\Report;
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

    protected function setUp(): void
    {
        parent::setUp();

        // Statamic reads its version from the site's composer.lock, which
        // testbench's skeleton doesn't have.
        // The control panel's built assets aren't published here.
        $this->withoutVite();

        Version::shouldReceive('get')->andReturn(Report::installedVersion('statamic/cms'));
    }

    protected function getPackageAliases($app): array
    {
        return ['Statamic' => Statamic::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('database.default', 'testing');
        // Users and roles the tests make live in a throwaway folder.
        $users = sys_get_temp_dir().'/porro-statamic-'.getmypid();
        $app['config']->set('statamic.stache.stores.users.directory', $users.'/users');
        $app['config']->set('statamic.users.repositories.file.paths.roles', $users.'/roles.yaml');
        $app['config']->set('statamic.users.repositories.file.paths.groups', $users.'/groups.yaml');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('porro.url', 'https://porro.test');

        // A buffer of its own per test, so no line leaks into the next.
        $app['config']->set('porro.activity.buffer', sys_get_temp_dir().'/porro-test-'.bin2hex(random_bytes(6)).'/activity.buf');
        $app['config']->set('statamic.users.repository', 'file');
        $app['config']->set('statamic.stache.watcher', false);
        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('statamic.static_caching.strategy', 'half');
        $app['config']->set('statamic.git.enabled', false);

        // Testbench runs as "testing", which would otherwise turn reporting off.
        $app['config']->set('porro.enabled', true);

        // Never touch the real crontab of whoever runs the tests.
        $app->instance(CrontabIo::class, new FakeCrontab(works: false));
    }
}
