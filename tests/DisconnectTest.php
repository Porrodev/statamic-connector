<?php

use Facades\Statamic\Updater\UpdatesOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as Sent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Illuminate\View\View;
use Porro\Core\SiteKey;
use Porro\Laravel\SiteKeys;
use Porro\Statamic\Panel;
use Statamic\Facades\Role;
use Statamic\Facades\User;

uses(RefreshDatabase::class);

const DISCONNECT_SITE = '01jabcdefghjkmnpqrstvwxyz0';
const DISCONNECT_HARBOUR = 'https://harbour-arts.org';

beforeEach(function () {
    UpdatesOverview::shouldReceive('count')->andReturn(0);
    UpdatesOverview::shouldReceive('hasStatamicUpdate')->andReturn(false);
    UpdatesOverview::shouldReceive('updatableAddons')->andReturn([]);
    UpdatesOverview::shouldReceive('hasSecurityUpdate')->andReturn(false);

    $this->app['env'] = 'production';
    config(['porro.key' => null, 'porro.enabled' => null, 'app.url' => DISCONNECT_HARBOUR]);
});

/**
 * Porro, faked: it takes the disconnect, or answers as told.
 *
 * @param  array{0: int, 1: array<string, mixed>}  $answer
 */
function porroTakesDisconnect(array $answer = [200, ['ok' => true]]): ArrayObject
{
    $porro = new ArrayObject(['disconnects' => []]);

    Http::fake([
        'porro.test/api/v1/disconnect' => function (Sent $request) use ($porro, $answer) {
            $porro['disconnects'][] = $request;

            return Http::response($answer[1], $answer[0]);
        },
        'porro.test/api/v1/findings' => Http::response(['ok' => true, 'site' => ['url' => 'https://porro.test/marlowe/sites/harbour-arts'], 'findings' => []]),
        '*' => Http::response([], 200),
    ]);

    return $porro;
}

/**
 * The site, connected from the control panel.
 */
function connectedFromPanel(?string $workspace = 'Marlowe Studio', ?string $host = 'harbour-arts.org'): void
{
    app(SiteKeys::class)->store(SiteKey::generate()->withSite(DISCONNECT_SITE), $workspace, $host);
}

function disconnectSuper()
{
    return tap(User::make()->email('sam@harbour-arts.org')->makeSuper())->save();
}

function disconnectEditor(string ...$permissions)
{
    $role = tap(Role::make('porro-off-'.substr(md5(implode(',', $permissions)), 0, 8))->addPermission(['access cp', ...$permissions]))->save();

    return tap(User::make()->email('editor-'.$role->handle().'@harbour-arts.org')->assignRole($role))->save();
}

/**
 * Site Health's HTML, whether Statamic renders it directly (5) or through
 * Inertia (6).
 */
function disconnectHealth($test, $user, array $session = []): string
{
    $response = $test->actingAs($user)->withSession($session)->get(DISCONNECT_HARBOUR.'/cp/porro')->assertOk();
    $page = $response->getOriginalContent() instanceof View ? ($response->getOriginalContent()->getData()['page'] ?? null) : null;

    return is_array($page) ? (string) ($page['props']['html'] ?? '') : $response->getContent();
}

function pressDisconnect($test, $user): TestResponse
{
    // Production checks the CSRF token.
    return $test->actingAs($user)->withSession(['_token' => 'csrf-token'])->post(DISCONNECT_HARBOUR.'/cp/porro/disconnect', ['_token' => 'csrf-token']);
}

describe('what the connected card shows', function () {
    it('gives Disconnect, and what it does, to someone who may connect', function () {
        porroTakesDisconnect();
        connectedFromPanel();

        $html = disconnectHealth($this, disconnectEditor(Panel::VIEW, Panel::CONNECT));

        expect($html)
            ->toContain('Connected to Marlowe Studio')
            ->toContain('Open in Porro')
            ->toContain('Disconnect harbour-arts.org from Porro?')
            ->toContain('This site stops sending reports to Marlowe Studio, and its key is deleted from this site.')
            ->toContain('Hourly reports of what is installed here, and signing in to this site from Porro.')
            ->toContain('The checks on this page. They run on this site and need no account.')
            ->toContain('Marlowe Studio keeps the site and its history. Uptime, SSL and domain checks go on from outside until someone removes the site there.')
            ->toContain('You can connect again at any time.')
            ->toContain('action="'.cp_route('porro.disconnect').'"')
            ->toContain('name="_token"');
    });

    it('keeps Disconnect from someone who may only look', function () {
        porroTakesDisconnect();
        connectedFromPanel();

        $html = disconnectHealth($this, disconnectEditor(Panel::VIEW, Panel::MANAGE));

        expect($html)
            ->toContain('Connected to Marlowe Studio')
            ->toContain('Open in Porro')
            ->not->toContain('<ui-modal')
            ->not->toContain('from Porro?')
            ->not->toContain(cp_route('porro.disconnect'));
    });

    it('says Porro when it doesn’t know the workspace, and this site without a host', function () {
        porroTakesDisconnect();
        connectedFromPanel(null, null);

        expect(disconnectHealth($this, disconnectSuper()))
            ->toContain('Disconnect this site from Porro?')
            ->toContain('This site stops sending reports to Porro, and its key is deleted from this site.')
            ->toContain('Porro keeps the site and its history.');
    });

    it('keeps a workspace name out of the control panel’s Vue templates', function () {
        porroTakesDisconnect();
        connectedFromPanel('{{ constructor.constructor("alert(1)")() }}');

        expect(disconnectHealth($this, disconnectSuper()))
            ->toMatch('/<div[^>]*v-pre[^>]*>\s*<p[^>]*>This site stops sending reports to \{\{ constructor/');
    });

    it('has no Disconnect for a key in the environment, only how to remove it', function () {
        porroTakesDisconnect();
        config(['porro.key' => siteKey(), 'porro.enabled' => true]);

        $may = disconnectHealth($this, disconnectSuper());
        $looks = disconnectHealth($this, disconnectEditor(Panel::VIEW));

        expect($may)
            ->toContain('To disconnect, remove PORRO_KEY from the environment and deploy. The control panel can’t change it.')
            ->not->toContain(cp_route('porro.disconnect'))
            ->not->toContain('Connected to Porro')
            ->and($looks)->not->toContain('To disconnect, remove PORRO_KEY');
    });
});

describe('disconnecting', function () {
    it('tells Porro, deletes the key and says so once', function () {
        $porro = porroTakesDisconnect();
        connectedFromPanel();
        $user = disconnectSuper();

        pressDisconnect($this, $user)
            ->assertRedirect(cp_route('porro.index'))
            ->assertSessionHas(Panel::DISCONNECTED, ['workspace' => 'Marlowe Studio', 'told' => true]);

        expect($porro['disconnects'])->toHaveCount(1)
            ->and($porro['disconnects'][0]->method())->toBe('POST')
            // Signed with the site's key, and nothing about who pressed it.
            ->and($porro['disconnects'][0]->body())->toBe('{}')
            ->and($porro['disconnects'][0]->hasHeader('X-Porro-Signature'))->toBeTrue()
            ->and((new SiteKeys)->token())->toBeNull()
            ->and((new SiteKeys)->source())->toBeNull();

        $html = disconnectHealth($this, $user, [Panel::DISCONNECTED => session(Panel::DISCONNECTED)]);

        expect($html)
            ->toContain('Disconnected from Marlowe Studio')
            ->toContain('This site no longer sends reports, and its key has been deleted. Marlowe Studio still has the site in Porro; someone in that workspace can remove it there.')
            ->toContain('Not connected to Porro')
            ->toContain('action="'.cp_route('porro.connect.start').'"')
            ->not->toContain('No longer connected to Porro');

        // The next page view is the plain card again.
        expect(disconnectHealth($this, $user))
            ->toContain('Not connected to Porro')
            ->not->toContain('Disconnected from Marlowe Studio');
    });

    it('still deletes the key when Porro doesn’t answer, and says Porro wasn’t told', function (array $answer) {
        $porro = porroTakesDisconnect($answer);
        connectedFromPanel();
        $user = disconnectSuper();

        pressDisconnect($this, $user)
            ->assertRedirect(cp_route('porro.index'))
            ->assertSessionHas(Panel::DISCONNECTED, ['workspace' => 'Marlowe Studio', 'told' => false]);

        expect($porro['disconnects'])->not->toBeEmpty()
            ->and((new SiteKeys)->token())->toBeNull();

        expect(disconnectHealth($this, $user, [Panel::DISCONNECTED => session(Panel::DISCONNECTED)]))
            ->toContain('Disconnected on this site')
            ->toContain('The key has been deleted and reports have stopped. Porro didn’t answer, so it hasn’t been told: Marlowe Studio will see this site as not reporting, not as disconnected.')
            ->toContain('Not connected to Porro')
            ->not->toContain('Disconnected from Marlowe Studio');
    })->with([
        'Porro down' => [[503, []]],
        'a clock that’s out' => [[401, ['ok' => false, 'error' => 'The request’s timestamp is too far from the server’s clock.']]],
    ]);

    it('counts a key Porro already turns away as told', function () {
        porroTakesDisconnect([401, ['ok' => false, 'error' => 'Invalid signature.']]);
        connectedFromPanel();

        pressDisconnect($this, disconnectSuper())->assertSessionHas(Panel::DISCONNECTED, ['workspace' => 'Marlowe Studio', 'told' => true]);

        expect((new SiteKeys)->token())->toBeNull();
    });

    it('needs the connect permission', function () {
        $porro = porroTakesDisconnect();
        connectedFromPanel();

        foreach ([disconnectEditor(Panel::VIEW), disconnectEditor(Panel::VIEW, Panel::MANAGE)] as $user) {
            pressDisconnect($this, $user)->assertForbidden();
        }

        expect($porro['disconnects'])->toBeEmpty()
            ->and((new SiteKeys)->token())->not->toBeNull();

        pressDisconnect($this, disconnectEditor(Panel::VIEW, Panel::CONNECT))->assertRedirect(cp_route('porro.index'));

        expect((new SiteKeys)->token())->toBeNull();
    });

    it('sends guests to sign in', function () {
        $porro = porroTakesDisconnect();
        connectedFromPanel();

        $this->withSession(['_token' => 'csrf-token'])->post(DISCONNECT_HARBOUR.'/cp/porro/disconnect', ['_token' => 'csrf-token'])->assertRedirect();

        expect($porro['disconnects'])->toBeEmpty()
            ->and((new SiteKeys)->token())->not->toBeNull();
    });

    it('only answers a POST, with a CSRF token', function () {
        $porro = porroTakesDisconnect();
        connectedFromPanel();
        $user = disconnectSuper();

        // A GET finds nothing there: the control panel answers 404, a bare router 405.
        expect($this->actingAs($user)->get(DISCONNECT_HARBOUR.'/cp/porro/disconnect')->status())->toBeIn([404, 405]);
        $this->actingAs($user)->withSession(['_token' => 'csrf-token'])->post(DISCONNECT_HARBOUR.'/cp/porro/disconnect', ['_token' => 'someone-else'])->assertStatus(419);

        expect($porro['disconnects'])->toBeEmpty()
            ->and((new SiteKeys)->token())->not->toBeNull();
    });

    it('refuses a key from the environment, and leaves a stored one alone', function () {
        $porro = porroTakesDisconnect();
        connectedFromPanel();
        config(['porro.key' => $key = siteKey()]);

        pressDisconnect($this, disconnectSuper())
            ->assertRedirect(cp_route('porro.index'))
            ->assertSessionHas('error', 'To disconnect, remove PORRO_KEY from the environment and deploy. The control panel can’t change it.')
            ->assertSessionMissing(Panel::DISCONNECTED);

        expect($porro['disconnects'])->toBeEmpty()
            ->and((new SiteKeys)->token())->toBe($key)
            ->and((new SiteKeys)->stored())->not->toBeNull();
    });

    it('refuses on a copy that isn’t the live site, which holds the live site’s key', function (string $env) {
        $porro = porroTakesDisconnect();
        connectedFromPanel();
        $this->app['env'] = $env;
        // Even one that has been told to report.
        config(['porro.enabled' => true]);
        $user = disconnectSuper();

        // No button to press.
        expect(disconnectHealth($this, $user))
            ->toContain('This is a development copy')
            ->not->toContain('<ui-modal')
            ->not->toContain(cp_route('porro.disconnect'));

        pressDisconnect($this, $user)
            ->assertRedirect(cp_route('porro.index'))
            ->assertSessionHas('error', fn ($error) => str_contains($error, "“{$env}”") && str_contains($error, 'live site'))
            ->assertSessionMissing(Panel::DISCONNECTED);

        expect($porro['disconnects'])->toBeEmpty()
            ->and((new SiteKeys)->token())->not->toBeNull();
    })->with(['local', 'staging']);

    it('says so when there’s nothing to disconnect', function () {
        $porro = porroTakesDisconnect();

        pressDisconnect($this, disconnectSuper())
            ->assertRedirect(cp_route('porro.index'))
            ->assertSessionHas('error', 'This site isn’t connected to Porro.')
            ->assertSessionMissing(Panel::DISCONNECTED);

        expect($porro['disconnects'])->toBeEmpty();
    });
});

describe('a key Porro turned away', function () {
    beforeEach(function () {
        porroTakesDisconnect();
        connectedFromPanel();

        Carbon::setTestNow('2026-11-16 14:02:00');
        app(SiteKeys::class)->turnAway();
        Carbon::setTestNow();
    });

    it('says what happened, with the button to connect again', function () {
        $html = disconnectHealth($this, disconnectSuper());

        expect($html)
            ->toContain('No longer connected to Porro')
            ->toContain('Porro turned this site’s key away')
            ->toContain('The site was removed from Marlowe Studio, or given a new key there. Reports stopped on 16 November at 14:02. The old key has been deleted from this site.')
            ->toContain('Connect again to carry on. Opens porro.test to sign in and approve.')
            ->toContain('action="'.cp_route('porro.connect.start').'"')
            ->toContain('Connect to Porro')
            ->not->toContain('Not connected to Porro')
            ->not->toContain('Have this site checked every hour')
            ->not->toContain('Watch this site from outside too');
    });

    it('gives the time in the site’s own timezone', function () {
        config(['app.timezone' => 'Europe/Paris']);

        expect(disconnectHealth($this, disconnectSuper()))->toContain('Reports stopped on 16 November at 15:02.');
    });

    it('keeps the wording and leaves the button out where the site can’t connect', function () {
        $looks = disconnectHealth($this, disconnectEditor(Panel::VIEW));

        $this->app['env'] = 'staging';
        $copy = disconnectHealth($this, disconnectSuper());

        foreach ([$looks, $copy] as $html) {
            expect($html)
                ->toContain('No longer connected to Porro')
                ->toContain('Porro turned this site’s key away')
                ->toContain('Connect again to carry on.')
                ->not->toContain('action="'.cp_route('porro.connect.start').'"')
                ->not->toContain('Watch this site from outside too');
        }
    });

    it('goes once the site connects again', function () {
        connectedFromPanel();

        expect(disconnectHealth($this, disconnectSuper()))
            ->toContain('Connected to Marlowe Studio')
            ->not->toContain('No longer connected to Porro');

        // And doesn't come back after disconnecting by hand.
        pressDisconnect($this, disconnectSuper());

        expect(disconnectHealth($this, disconnectSuper()))
            ->toContain('Not connected to Porro')
            ->not->toContain('No longer connected to Porro');
    });

    it('says nothing of it over a key in the environment', function () {
        config(['porro.key' => siteKey(), 'porro.enabled' => true]);

        expect(disconnectHealth($this, disconnectSuper()))
            ->not->toContain('No longer connected to Porro')
            ->not->toContain('Porro turned this site’s key away');
    });
});

describe('on Statamic 5', function () {
    // The suite runs on whichever Statamic is installed, so the classic
    // control panel's views are rendered on their own here.
    $connect = fn (string $state, array $more = []) => view('porro::v5.connect', ['connect' => $more + [
        'state' => $state,
        'button' => false,
        'url' => 'https://harbour-arts.org/cp/porro/connect',
        'mark' => 'data:image/svg+xml;base64,AAAA',
        'environment' => 'production',
        'porroHost' => 'porro.dev',
        'workspace' => 'Marlowe Studio',
        'approvedAgo' => '2 days ago',
        'openUrl' => 'https://app.porro.dev',
        'canDisconnect' => false,
        'disconnectUrl' => 'https://harbour-arts.org/cp/porro/disconnect',
        'host' => 'harbour-arts.org',
        'envKey' => false,
        'turnedAway' => null,
    ]])->render();

    it('opens a dialog from the Disconnect button, which posts only once confirmed', function () use ($connect) {
        $html = $connect('connected', ['canDisconnect' => true]);

        expect($html)
            ->toContain('data-porro-open="porro-disconnect"')
            ->toContain('<dialog id="porro-disconnect"')
            ->toContain('Disconnect harbour-arts.org from Porro?')
            ->toContain('This site stops sending reports to Marlowe Studio, and its key is deleted from this site.')
            ->toContain('You can connect again at any time.')
            ->toContain('action="https://harbour-arts.org/cp/porro/disconnect"')
            ->toContain('name="_token"')
            ->toContain('formmethod="dialog"')
            ->toContain('class="btn-danger"')
            ->toContain('href="https://app.porro.dev"');

        // The button that opens it sends nothing by itself.
        expect($html)->toMatch('/<button type="button"[^>]*data-porro-open/');
    });

    it('has no Disconnect for someone who may only look', function () use ($connect) {
        expect($connect('connected'))
            ->toContain('Connected to Marlowe Studio')
            ->not->toContain('Disconnect')
            ->not->toContain('<form');
    });

    it('says when Porro turned the key away', function () use ($connect) {
        $away = ['turnedAway' => ['workspace' => null, 'date' => '16 November', 'time' => '14:02']];

        expect($connect('ready', $away + ['button' => true]))
            ->toContain('No longer connected to Porro')
            ->toContain('Porro turned this site’s key away')
            ->toContain('The site was removed from Porro, or given a new key there. Reports stopped on 16 November at 14:02. The old key has been deleted from this site.')
            ->toContain('Connect again to carry on. Opens porro.dev to sign in and approve.')
            ->toContain('action="https://harbour-arts.org/cp/porro/connect"')
            ->and($connect('development', $away))
            ->toContain('Porro turned this site’s key away')
            ->not->toContain('<form');
    });

    it('shows the notice after disconnecting', function () {
        $html = view('porro::v5.disconnected', ['disconnected' => ['title' => 'Disconnected from Marlowe Studio', 'body' => 'This site no longer sends reports, and its key has been deleted.']])->render();

        expect($html)
            ->toContain('role="status"')
            ->toContain('v-pre')
            ->toContain('Disconnected from Marlowe Studio')
            ->toContain('This site no longer sends reports, and its key has been deleted.');
    });
});

describe('php please porro:disconnect', function () {
    it('asks first, then tells Porro and deletes the key', function () {
        $porro = porroTakesDisconnect();
        connectedFromPanel();

        // `php please porro:disconnect` runs this command.
        $this->artisan('statamic:porro:disconnect')
            ->expectsConfirmation('Disconnect harbour-arts.org from Marlowe Studio?', 'yes')
            ->expectsOutputToContain('Disconnected. Porro has been told.')
            ->expectsOutputToContain('The key has been deleted from this site’s database.')
            ->assertSuccessful();

        expect($porro['disconnects'])->toHaveCount(1)
            ->and((new SiteKeys)->token())->toBeNull();
    });

    it('leaves everything alone on a no', function () {
        $porro = porroTakesDisconnect();
        connectedFromPanel();

        $this->artisan('statamic:porro:disconnect')
            ->expectsConfirmation('Disconnect harbour-arts.org from Marlowe Studio?', 'no')
            ->assertSuccessful();

        expect($porro['disconnects'])->toBeEmpty()
            ->and((new SiteKeys)->token())->not->toBeNull();
    });

    it('fails on a copy that isn’t the live site, unless told it is with --live', function () {
        $porro = porroTakesDisconnect();
        connectedFromPanel();
        $this->app['env'] = 'staging';

        $this->artisan('statamic:porro:disconnect', ['--force' => true])
            ->expectsOutputToContain('not the live site')
            ->assertFailed();

        expect($porro['disconnects'])->toBeEmpty()
            ->and((new SiteKeys)->token())->not->toBeNull();

        // `php please porro:disconnect --live --force` passes both through.
        $this->artisan('statamic:porro:disconnect', ['--live' => true, '--force' => true])
            ->expectsOutputToContain('Disconnected. Porro has been told.')
            ->assertSuccessful();

        expect($porro['disconnects'])->toHaveCount(1)
            ->and((new SiteKeys)->token())->toBeNull();
    });

    it('says when Porro wasn’t told, without asking under --force', function () {
        porroTakesDisconnect([503, []]);
        connectedFromPanel();

        $this->artisan('statamic:porro:disconnect', ['--force' => true])
            ->expectsOutputToContain('Disconnected on this site. Porro didn’t answer, so it hasn’t been told: it will show this site as not reporting.')
            ->assertSuccessful();

        expect((new SiteKeys)->token())->toBeNull();
    });

    it('can’t change a key in the environment', function () {
        $porro = porroTakesDisconnect();
        config(['porro.key' => siteKey()]);

        $this->artisan('statamic:porro:disconnect')
            ->expectsOutputToContain('This site is connected with PORRO_KEY in its environment, which this command can’t change. Remove PORRO_KEY there and deploy.')
            ->assertFailed();

        expect($porro['disconnects'])->toBeEmpty();
    });

    it('says so when the site isn’t connected', function () {
        porroTakesDisconnect();

        $this->artisan('statamic:porro:disconnect')
            ->expectsOutputToContain('This site isn’t connected to Porro.')
            ->assertSuccessful();
    });
});
