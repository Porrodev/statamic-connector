<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Porro\Core\CrontabIo;
use Porro\Core\Report;
use Porro\Laravel\Reporter;
use Porro\Statamic\StatamicCollector;
use Porro\Statamic\Tests\FakeCrontab;

function statamicLayer(): ?array
{
    return collect(app(Reporter::class)->build()->toArray()['layers'])->firstWhere('name', 'statamic');
}

it('adds a Statamic layer to the Laravel report', function () {
    $layer = statamicLayer();

    expect($layer)->not->toBeNull()
        ->and($layer['version'])->toBe(Report::installedVersion('statamic/cms'))->not->toBeNull()
        ->and($layer['data'])->toMatchArray([
            'pro' => true,
            'addons' => [],
            'static_caching' => 'half',
            'git' => false,
            'stache_watcher' => false,
        ]);
});

it('reports a Solo site as not Pro', function () {
    config(['statamic.editions.pro' => false]);

    expect(statamicLayer()['data']['pro'])->toBeFalse();
});

it('sends the database block from the Laravel report', function () {
    $database = app(Reporter::class)->build()->toArray()['database'];

    expect($database)->toMatchArray(['engine' => 'sqlite', 'local' => null, 'managed' => null])
        ->and($database['version'])->not->toBeNull();
});

it('connects with php please porro:connect', function () {
    $env = tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($env, "APP_NAME=Test\n");
    $this->app->loadEnvironmentFrom(basename($env));
    $this->app->useEnvironmentPath(dirname($env));
    $this->app->setBasePath(dirname(__DIR__));
    $key = siteKey();
    Http::fake(['porro.test/*' => Http::response(['ok' => true], 202)]);

    // `php please porro:connect` runs this command.
    $this->artisan('statamic:porro:connect', ['key' => $key, '--no-cron' => true])->assertSuccessful();

    expect(file_get_contents($env))->toContain("PORRO_KEY={$key}");
    Http::assertSent(fn (Request $request) => $request->url() === 'https://porro.test/api/v1/reports'
        && collect(json_decode($request->body(), true)['layers'])->contains('name', 'statamic'));
});

it('is registered as a Porro collector', function () {
    expect(collect(app()->tagged('porro.collectors'))->map(fn ($c) => $c::class))->toContain(StatamicCollector::class);
});

it('offers to set up cron from php please porro:connect', function () {
    $env = tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($env, "APP_NAME=Test\n");
    $this->app->loadEnvironmentFrom(basename($env));
    $this->app->useEnvironmentPath(dirname($env));
    $this->app->setBasePath(dirname(__DIR__));
    $this->app->instance(CrontabIo::class, $crontab = new FakeCrontab);
    Http::fake(['porro.test/*' => Http::response(['ok' => true], 202)]);

    $this->artisan('statamic:porro:connect', ['key' => siteKey()])
        ->expectsConfirmation('Reports run on the scheduler, which needs one cron line. Add it to this server’s crontab now?', 'yes')
        ->assertSuccessful();

    expect($crontab->contents)->toContain('artisan schedule:run');
});

it('adds cron on its own with php please porro:cron', function () {
    $this->app->instance(CrontabIo::class, $crontab = new FakeCrontab);

    $this->artisan('statamic:porro:cron')->assertSuccessful();

    expect($crontab->contents)->toContain('artisan schedule:run');
});
