<?php

use Facades\Statamic\Updater\UpdatesOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as Sent;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Illuminate\View\View;
use Porro\Core\SiteKey;
use Porro\Laravel\SiteKeys;
use Porro\Statamic\Panel;
use Statamic\Facades\Permission;
use Statamic\Facades\Role;
use Statamic\Facades\User;

uses(RefreshDatabase::class);

const CONNECT_REQUEST = '0196f3a2-7c41-7e0b-9d55-2f6a8c1e4b70';
const CONNECT_SITE = '01jabcdefghjkmnpqrstvwxyz0';
const CONNECT_NEXT = 'https://porro.test/connect/'.CONNECT_REQUEST;
const HARBOUR = 'https://harbour-arts.org';

beforeEach(function () {
    UpdatesOverview::shouldReceive('count')->andReturn(0);
    UpdatesOverview::shouldReceive('hasStatamicUpdate')->andReturn(false);
    UpdatesOverview::shouldReceive('updatableAddons')->andReturn([]);
    UpdatesOverview::shouldReceive('hasSecurityUpdate')->andReturn(false);

    $this->app['env'] = 'production';
    config(['porro.key' => null, 'porro.enabled' => null, 'app.url' => HARBOUR]);
});

/**
 * Porro, faked. The connect calls must be signed by the public key the
 * site sent when it asked.
 *
 * @param  array<string, array{0: int, 1: array<string, mixed>}>  $answers
 */
function porroFake(array $answers = []): ArrayObject
{
    $porro = new ArrayObject(['public_key' => null, 'starts' => [], 'completes' => [], 'reports' => []]);

    $signed = function (Sent $request, string $path) use ($porro): bool {
        $headers = array_map(fn ($v) => $v[0], $request->headers());
        $message = "connector-v1\nPOST\n{$path}\n".$headers['X-Porro-Timestamp']."\n".$headers['X-Porro-Nonce']."\n".hash('sha256', $request->body());

        return sodium_crypto_sign_verify_detached(base64_decode($headers['X-Porro-Signature']), $message, base64_decode((string) $porro['public_key']));
    };

    Http::fake([
        'porro.test/api/v1/connect' => function (Sent $request) use ($porro, $answers, $signed) {
            $porro['starts'][] = $request;
            $porro['public_key'] = $request['public_key'];
            [$status, $body] = $answers['start'] ?? [201, ['ok' => true, 'id' => CONNECT_REQUEST, 'approve_url' => CONNECT_NEXT]];

            return $signed($request, '/api/v1/connect') ? Http::response($body, $status) : Http::response(['ok' => false, 'error' => 'Invalid signature.'], 401);
        },
        'porro.test/api/v1/connect/*/complete' => function (Sent $request) use ($porro, $answers, $signed) {
            $porro['completes'][] = $request;
            [$status, $body] = $answers['complete'] ?? [200, ['ok' => true, 'site' => CONNECT_SITE, 'host' => 'harbour-arts.org', 'workspace' => 'Marlowe Studio']];

            return $signed($request, '/api/v1/connect/'.CONNECT_REQUEST.'/complete') ? Http::response($body, $status) : Http::response(['ok' => false, 'error' => 'Invalid signature.'], 401);
        },
        'porro.test/api/v1/reports' => function (Sent $request) use ($porro) {
            $porro['reports'][] = $request;

            return Http::response(['ok' => true], 202);
        },
        'porro.test/api/v1/findings' => Http::response(['ok' => true, 'site' => ['url' => 'https://porro.test/marlowe/sites/harbour-arts'], 'findings' => []]),
        '*' => Http::response([], 200),
    ]);

    return $porro;
}

function connectSuper()
{
    return tap(User::make()->email('sam@harbour-arts.org')->makeSuper())->save();
}

function connectEditor(string ...$permissions)
{
    $role = tap(Role::make('porro-'.substr(md5(implode(',', $permissions)), 0, 8))->addPermission(['access cp', ...$permissions]))->save();

    return tap(User::make()->email('editor-'.$role->handle().'@harbour-arts.org')->assignRole($role))->save();
}

/**
 * Site Health's HTML, whether Statamic renders it directly (5) or through
 * Inertia (6).
 */
function healthHtml(TestResponse $response): string
{
    $page = $response->getOriginalContent() instanceof View ? ($response->getOriginalContent()->getData()['page'] ?? null) : null;

    return is_array($page) ? (string) ($page['props']['html'] ?? '') : $response->getContent();
}

function siteHealth($test, $user): string
{
    return healthHtml($test->actingAs($user)->get(HARBOUR.'/cp/porro')->assertOk());
}

function press($test, $user, string $site = HARBOUR): TestResponse
{
    // Production checks the CSRF token.
    return $test->actingAs($user)->withSession(['_token' => 'csrf-token'])->post($site.'/cp/porro/connect', ['_token' => 'csrf-token']);
}

function returnFromPorro($test, $user, array $query = []): TestResponse
{
    $query += ['porro_request' => CONNECT_REQUEST, 'porro_grant' => 'the-grant', 'porro_next' => CONNECT_NEXT];

    return $test->actingAs($user)->get(HARBOUR.'/cp/porro/connect?'.http_build_query($query));
}

describe('what Site Health shows', function () {
    it('shows the button on the live site to someone who may connect', function () {
        porroFake();

        $html = siteHealth($this, connectSuper());

        expect($html)
            ->toContain('Not connected to Porro')
            ->toContain('Have this site checked every hour')
            ->toContain('Opens porro.test to sign in and approve. No key to copy.')
            ->toContain('Connect to Porro')
            ->toContain('action="'.cp_route('porro.connect.start').'"')
            ->toContain('data:image/svg+xml;base64,')
            // The button replaces "run this command".
            ->not->toContain('Watch this site from outside too')
            ->not->toContain('porro:connect');
    });

    it('gives the button to an editor with the connect permission, and only them', function () {
        porroFake();

        $with = siteHealth($this, connectEditor(Panel::VIEW, Panel::CONNECT));
        $without = siteHealth($this, connectEditor(Panel::VIEW, Panel::MANAGE));

        expect($with)->toContain('action="'.cp_route('porro.connect.start').'"')
            ->and($without)->not->toContain('action="'.cp_route('porro.connect.start').'"')
            ->and($without)->not->toContain('Not connected to Porro')
            // Without it they see what they saw before.
            ->and($without)->toContain('Watch this site from outside too');
    });

    it('lists the permission under Porro', function () {
        Permission::boot();

        expect(Permission::get(Panel::CONNECT))->not->toBeNull()
            ->and(Permission::get(Panel::CONNECT)->label())->toBe('Connect the site to Porro');
    });

    it('shows developer copy, not the button, on a copy that isn’t the live site', function (string $env) {
        porroFake();
        $this->app['env'] = $env;
        // Even one that has been told to report.
        config(['porro.enabled' => true]);

        $html = siteHealth($this, connectSuper());

        expect($html)
            ->toContain('Not connected to Porro')
            ->toContain('This is a development copy')
            ->toContain("Its environment is “{$env}”, so it does not connect and sends no reports.")
            ->toContain('You can also set a site key.')
            ->not->toContain('action="'.cp_route('porro.connect.start').'"')
            ->not->toContain('Have this site checked every hour')
            ->not->toContain('Watch this site from outside too');
    })->with(['local', 'staging']);

    it('shows nothing new when the site key is in the environment', function (string $env) {
        porroFake();
        $this->app['env'] = $env;
        config(['porro.key' => siteKey(), 'porro.enabled' => true]);

        $html = siteHealth($this, connectSuper());

        expect($html)
            ->toContain('From Porro')
            ->not->toContain('Connect to Porro')
            ->not->toContain('Connected to Porro')
            ->not->toContain('Not connected to Porro')
            ->not->toContain('This is a development copy')
            ->not->toContain('Watch this site from outside too');
    })->with(['production', 'local']);

    it('says who the site is connected to once it’s connected from here', function () {
        porroFake();
        app(SiteKeys::class)->store(SiteKey::generate()->withSite(CONNECT_SITE), 'Marlowe Studio', 'harbour-arts.org');

        // Anyone who can see Site Health sees it, connect permission or not.
        $html = siteHealth($this, connectEditor(Panel::VIEW));

        expect($html)
            ->toContain('Connected to Porro')
            ->toContain('Connected to Marlowe Studio')
            ->toContain('Approved a moment ago.')
            ->toContain('The key was made on this site and is kept encrypted in its database.')
            ->toContain('Open in Porro')
            ->toContain('https://porro.test/marlowe/sites/harbour-arts')
            ->toContain('From Porro')
            ->not->toContain('action="'.cp_route('porro.connect.start').'"')
            ->not->toContain('Watch this site from outside too');
    });

    it('keeps a workspace name out of the control panel’s Vue templates', function () {
        porroFake();
        app(SiteKeys::class)->store(SiteKey::generate()->withSite(CONNECT_SITE), '{{ constructor.constructor("alert(1)")() }}');

        $html = siteHealth($this, connectSuper());

        expect($html)->toMatch('/<p[^>]*v-pre[^>]*>Connected to \{\{ constructor/');
    });
});

describe('connecting', function () {
    it('goes to Porro to approve, then finishes and keeps the key', function () {
        $porro = porroFake();
        $user = connectSuper();

        press($this, $user)->assertRedirect(CONNECT_NEXT);

        expect($porro['starts'][0]['address'])->toBe(HARBOUR)
            ->and($porro['starts'][0]['return_url'])->toBe(HARBOUR.'/cp/porro/connect')
            ->and($porro['starts'][0]['connector']['name'])->toBe('porro/statamic-connector')
            ->and($porro['starts'][0]['platform'])->toMatchArray(['name' => 'statamic', 'environment' => 'production'])
            ->and((new SiteKeys)->token())->toBeNull();

        returnFromPorro($this, $user)->assertRedirect(CONNECT_NEXT);

        $key = SiteKey::parse((string) (new SiteKeys)->token());

        expect($key->publicKey())->toBe($porro['public_key'])
            ->and($key->siteId)->toBe(strtoupper(CONNECT_SITE))
            ->and($porro['reports'])->toHaveCount(1)
            ->and($porro['reports'][0]['trigger'])->toBe('connect');

        // Porro's page sends the person back; the panel now says connected.
        $this->actingAs($user)->get(HARBOUR.'/cp/porro/connect')->assertRedirect(cp_route('porro.index'));

        expect(siteHealth($this, $user))->toContain('Connected to Marlowe Studio');
    });

    it('needs the connect permission to start or finish', function () {
        $porro = porroFake();

        foreach ([connectEditor(Panel::VIEW), connectEditor(Panel::VIEW, Panel::MANAGE)] as $user) {
            press($this, $user)->assertForbidden();
            returnFromPorro($this, $user)->assertForbidden();
        }

        expect($porro['starts'])->toBeEmpty()
            ->and($porro['completes'])->toBeEmpty();

        press($this, connectEditor(Panel::VIEW, Panel::CONNECT))->assertRedirect(CONNECT_NEXT);
    });

    it('sends guests to sign in', function () {
        $porro = porroFake();

        $this->withSession(['_token' => 'csrf-token'])->post(HARBOUR.'/cp/porro/connect', ['_token' => 'csrf-token'])->assertRedirect();
        $this->get(HARBOUR.'/cp/porro/connect?porro_request='.CONNECT_REQUEST.'&porro_grant=the-grant')->assertRedirect();

        expect($porro['starts'])->toBeEmpty()
            ->and($porro['completes'])->toBeEmpty();
    });

    it('never ties a request to nobody', function () {
        $porro = porroFake();

        // Signed in as far as the permission check goes, but with no id.
        $ghost = Mockery::mock(connectSuper())->makePartial();
        $ghost->shouldReceive('id')->andReturn(null);
        User::shouldReceive('current')->andReturn($ghost);

        $this->withoutMiddleware()->post(HARBOUR.'/cp/porro/connect')->assertForbidden();

        // The permission check alone would have let them through.
        expect(Panel::can(Panel::CONNECT))->toBeTrue()
            ->and($porro['starts'])->toBeEmpty();
    });

    it('won’t let another signed-in person finish someone’s request', function () {
        $porro = porroFake();

        press($this, connectSuper());

        returnFromPorro($this, connectEditor(Panel::VIEW, Panel::CONNECT))
            ->assertRedirect(cp_route('porro.index'))
            ->assertSessionHas('error', fn ($error) => str_contains($error, 'different request'));

        expect($porro['completes'])->toBeEmpty()
            ->and((new SiteKeys)->token())->toBeNull();
    });

    it('lands back on Site Health with the reason when it can’t connect', function (array $answers, string $says) {
        porroFake($answers);
        $user = connectSuper();

        $response = press($this, $user);

        if ($response->isRedirect(CONNECT_NEXT)) {
            $response = returnFromPorro($this, $user);
        }

        $response->assertRedirect(cp_route('porro.index'))->assertSessionHas('error', fn ($error) => str_contains($error, $says));

        expect((new SiteKeys)->token())->toBeNull()
            // Shown on the page itself.
            ->and(healthHtml($this->actingAs($user)->withSession(['error' => session('error')])->get(HARBOUR.'/cp/porro')))->toContain(e($says));
    })->with([
        'turned down' => [['complete' => [403, ['ok' => false, 'error' => 'The request was turned down in the browser.']]], 'turned down'],
        'expired' => [['complete' => [410, ['ok' => false, 'error' => 'This request has expired or was already used.']]], 'ran out of time'],
        'wrong grant' => [['complete' => [403, ['ok' => false, 'error' => 'That grant doesn’t match this request.']]], 'accept that approval'],
        'Porro down' => [['start' => [503, []]], 'reach Porro'],
    ]);

    it('doesn’t start from a development copy or over an environment key', function () {
        $porro = porroFake();
        $user = connectSuper();

        $this->app['env'] = 'local';
        press($this, $user)->assertSessionHas('error', fn ($error) => str_contains($error, '“local”'));

        $this->app['env'] = 'production';
        config(['porro.key' => siteKey()]);
        press($this, $user)->assertSessionHas('error', fn ($error) => str_contains($error, 'PORRO_KEY'));

        expect($porro['starts'])->toBeEmpty();
    });

    it('explains a proxy it doesn’t trust rather than ask for http', function () {
        $porro = porroFake();

        $this->actingAs(connectSuper())->withSession(['_token' => 'csrf-token'])->withHeaders(['Origin' => HARBOUR])
            ->post('http://harbour-arts.org/cp/porro/connect', ['_token' => 'csrf-token'])
            ->assertSessionHas('error', fn ($error) => str_contains($error, 'proxy'));

        expect($porro['starts'])->toBeEmpty();
    });
});

describe('on Statamic 5', function () {
    // The suite runs on whichever Statamic is installed, so the classic
    // control panel's view is rendered on its own here.
    $connect = fn (string $state, array $more = []) => view('porro::v5.connect', ['connect' => $more + [
        'state' => $state,
        'url' => 'https://harbour-arts.org/cp/porro/connect',
        'mark' => 'data:image/svg+xml;base64,AAAA',
        'environment' => 'staging',
        'porroHost' => 'porro.dev',
        'workspace' => null,
        'approvedAgo' => null,
        'openUrl' => 'https://app.porro.dev',
    ]])->render();

    it('shows the button on the live site', function () use ($connect) {
        expect($connect('ready'))
            ->toContain('Not connected to Porro')
            ->toContain('Have this site checked every hour')
            ->toContain('Opens porro.dev to sign in and approve. No key to copy.')
            ->toContain('action="https://harbour-arts.org/cp/porro/connect"')
            ->toContain('name="_token"')
            ->toContain('Connect to Porro');
    });

    it('shows developer copy without the button', function () use ($connect) {
        expect($connect('development'))
            ->toContain('This is a development copy')
            ->toContain('Its environment is “staging”')
            ->not->toContain('<form');
    });

    it('says who it’s connected to', function () use ($connect) {
        expect($connect('connected', ['workspace' => 'Marlowe Studio', 'approvedAgo' => '2 days ago']))
            ->toContain('Connected to Porro')
            ->toContain('Connected to Marlowe Studio')
            ->toContain('Approved 2 days ago.')
            ->toContain('href="https://app.porro.dev"')
            ->not->toContain('<form');
    });
});
