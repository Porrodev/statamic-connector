<?php

use Facades\Statamic\Updater\UpdatesOverview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Illuminate\View\View;
use Porro\Statamic\SiteHealthWidget;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Role;
use Statamic\Facades\User;

beforeEach(function () {
    UpdatesOverview::shouldReceive('count')->andReturn(2);
    UpdatesOverview::shouldReceive('hasStatamicUpdate')->andReturn(true);
    UpdatesOverview::shouldReceive('updatableAddons')->andReturn(['acme/seo']);
    UpdatesOverview::shouldReceive('hasSecurityUpdate')->andReturn(false);

    Http::fake([
        'endoflife.date/*' => Http::response([['cycle' => '6', 'eol' => '2027-12-31'], ['cycle' => '5', 'eol' => '2026-01-31']]),
        'packagist.org/api/security-advisories/' => Http::response(['advisories' => []]),
        'repo.packagist.org/*' => Http::response(['packages' => []]),
        'porro.test/api/v1/findings' => Http::response(['ok' => true, 'site' => ['url' => 'https://porro.test/acme/sites/example'], 'findings' => [
            ['kind' => 'ssl_expiring', 'rank' => 3, 'title' => 'SSL certificate expires in 9 days', 'detail' => 'Issued by R11', 'scope' => 'site', 'url' => 'https://porro.test/acme/sites/example'],
            ['kind' => 'os_unsupported', 'rank' => 2, 'title' => 'Ubuntu 20.04 past security support {{ constructor }}', 'scope' => 'server'],
        ]]),
    ]);
});

function superUser()
{
    return tap(User::make()->email('admin@example.com')->makeSuper())->save();
}

/**
 * The utility's HTML, whether Statamic renders it directly (5) or through
 * Inertia (6).
 */
function utilityHtml(TestResponse $response): string
{
    $page = $response->getOriginalContent() instanceof View ? ($response->getOriginalContent()->getData()['page'] ?? null) : null;

    return is_array($page) ? (string) ($page['props']['html'] ?? '') : $response->getContent();
}

function utilityUrl(): string
{
    return cp_route('porro.index');
}

it('shows the site’s health without a Porro account, with the connect command', function () {
    config(['porro.key' => null]);

    $html = utilityHtml($this->actingAs(superUser())->get(utilityUrl())->assertOk());

    expect($html)
        ->toContain('Porro Site Health')
        ->toContain('Checked on this site')
        ->toContain('2 updates available')
        ->toContain('Scheduler isn’t running')
        ->toContain('Watch this site from outside too')
        ->toContain('php please porro:connect &lt;site key&gt;')
        ->not->toContain('From Porro');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'porro.test'));
});

it('adds the site’s Porro findings once connected', function () {
    config(['porro.key' => siteKey()]);

    $html = utilityHtml($this->actingAs(superUser())->get(utilityUrl())->assertOk());

    expect($html)
        ->toContain('From Porro')
        ->toContain('SSL certificate expires in 9 days')
        ->toContain('https://porro.test/acme/sites/example')
        ->not->toContain('Watch this site from outside too');
});

it('keeps text from outside out of the control panel’s Vue templates', function () {
    config(['porro.key' => siteKey()]);

    $html = utilityHtml($this->actingAs(superUser())->get(utilityUrl()));

    // The title is escaped by Blade and sits inside a v-pre element.
    expect($html)->toMatch('/<div v-pre[^>]*>\s*<p[^>]*>\s*(<span[^>]*>[^<]*<\/span>\s*)?Ubuntu 20.04 past security support \{\{ constructor \}\}/');
});

it('is only for users with the Site Health permission', function () {
    $editor = tap(User::make()->email('editor@example.com'))->save();
    $role = tap(Role::make('editor')->addPermission('access cp'))->save();
    $editor->assignRole($role)->save();

    // Statamic sends users away from pages they can't open.
    expect($this->actingAs($editor)->get(utilityUrl())->status())->toBeIn([302, 403]);
    $refresh = $this->actingAs($editor)->post(cp_route('porro.refresh'));
    expect($refresh->status())->toBeIn([302, 403])
        ->and($refresh->headers->get('Location'))->not->toBe(utilityUrl());
    Http::assertNothingSent();

    $role->addPermission('view porro site health')->save();

    $this->actingAs($editor->fresh())->get(utilityUrl())->assertOk();
});

it('checks again on request', function () {
    config(['porro.key' => siteKey()]);
    $this->actingAs(superUser())->get(utilityUrl());
    $this->actingAs(superUser())->get(utilityUrl());
    $sent = count(Http::recorded());

    $this->actingAs(superUser())->post(cp_route('porro.refresh'))->assertRedirect(utilityUrl());

    // Porro is asked again; the public data keeps its own cache.
    $recorded = Http::recorded();
    expect(count($recorded))->toBe($sent + 1)
        ->and($recorded->last()[0]->url())->toBe('https://porro.test/api/v1/findings');
});

it('shows what it can of a malformed result instead of breaking', function () {
    Cache::put('porro:health:panel', [
        'connected' => true,
        'porro' => ['ok' => true, 'site_url' => 'javascript:alert(1)', 'findings' => 'nope'],
        'issues' => [
            'nope',
            ['title' => ['nope']],
            ['kind' => 'debug_mode', 'severity' => 'boom', 'title' => 'Debug mode is on in production', 'detail' => ['x'], 'url' => 'javascript:alert(1)', 'style' => 'x'],
        ],
        'counts' => ['critical' => 'lots'],
        'checked' => 'everything',
        'unavailable' => [['x'], 'Couldn’t reach Packagist'],
        'checked_at' => 'yesterday-ish?',
        'commands' => 'nope',
    ], 300);

    $html = utilityHtml($this->actingAs(superUser())->get(utilityUrl())->assertOk());

    expect($html)->toContain('Debug mode is on in production')
        ->toContain('Couldn’t reach Packagist')
        ->not->toContain('javascript:')
        ->not->toContain('The checks couldn’t run just now');

    $widget = new SiteHealthWidget;
    $widget->setConfig(['limit' => 3]);
    expect((string) $widget->html())->toContain('Debug mode is on in production')->not->toContain('javascript:');
});

it('shows the unavailable state when the cached result isn’t a result at all', function () {
    Cache::put('porro:health:panel', 'nope', 300);

    $html = utilityHtml($this->actingAs(superUser())->get(utilityUrl())->assertOk());
    expect($html)->toContain('The checks couldn’t run just now');

    expect((string) (new SiteHealthWidget)->html())->toContain('Not checked yet');
});

it('offers a dashboard widget for users who can see the utility', function () {
    $widget = new SiteHealthWidget;
    $widget->setConfig(['limit' => 1]);

    $this->actingAs(superUser());

    // The dashboard never waits on the checks: until they've run, the
    // widget says so.
    expect((string) $widget->html())->toContain('Not checked yet');
    Http::assertNothingSent();

    $this->get(utilityUrl());
    $html = (string) $widget->html();

    expect($html)->toContain('Porro Site Health')->toMatch('/See \\d+ more/')->toContain(utilityUrl());

    $this->actingAs(tap(User::make()->email('nobody@example.com'))->save());

    expect((string) (new SiteHealthWidget)->html())->toBe('');
});

it('keeps the old utility address working', function () {
    $this->actingAs(superUser())->get(cp_route('utilities.index').'/porro')->assertRedirect(utilityUrl());
});

it('has its own Porro section in the nav', function () {
    $this->actingAs(superUser());

    $items = collect(Nav::build())->flatMap(fn ($section) => collect($section['items'] ?? [])->map(fn ($item) => [$section['display'] ?? null, $item->display()]));

    expect($items->all())->toContain(['Porro', 'Site Health'], ['Porro', 'Blocking']);
});
