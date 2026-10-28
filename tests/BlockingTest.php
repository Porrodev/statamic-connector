<?php

use Facades\Statamic\Updater\UpdatesOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Porro\Core\Activity\AbuseIpdb;
use Porro\Core\Activity\Cloudflare;
use Porro\Core\Activity\Entry;
use Porro\Core\Activity\IpDetails;
use Porro\Core\Activity\Recorder;
use Porro\Laravel\Activity\Activity;
use Porro\Laravel\Activity\CloudflareMode;
use Porro\Laravel\Activity\Confirm;
use Porro\Laravel\Activity\NginxServers;
use Porro\Statamic\Panel;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use Statamic\Statamic;

uses(RefreshDatabase::class);

beforeEach(function () {
    UpdatesOverview::shouldReceive('count')->andReturn(0);
    UpdatesOverview::shouldReceive('hasStatamicUpdate')->andReturn(false);
    UpdatesOverview::shouldReceive('updatableAddons')->andReturn([]);
    UpdatesOverview::shouldReceive('hasSecurityUpdate')->andReturn(false);
    Http::fake(['*' => Http::response([], 200)]);
});

function admin()
{
    return tap(User::make()->email('admin@example.com')->makeSuper())->save();
}

function editorWith(string ...$permissions)
{
    $role = tap(Role::make('porro-editor')->addPermission(['access cp', ...$permissions]))->save();

    return tap(User::make()->email('editor@example.com')->assignRole($role))->save();
}

function pageHtml($response): string
{
    return $response->getContent();
}

it('lists the blocklist, safelist and protected ranges', function () {
    config(['porro.blocklist.safelist' => [['cidr' => '81.2.69.0/24', 'note' => 'Office']]]);
    DB::table('porro_blocklist')->insert((new Entry('103.75.190.0/24', 'Scanner network', Entry::MANUAL, time(), null, 'Daniel'))->toArray());

    $html = pageHtml($this->actingAs(admin())->get(cp_route('porro.blocking'))->assertOk());

    expect($html)
        ->toContain('Where blocks are enforced')
        ->toContain('103.75.190.0/24')
        ->toContain('Scanner network')
        ->toContain('81.2.69.0/24')
        ->toContain('From config')
        ->toContain('Laravel Forge')
        ->toContain('Forge health checks')
        ->toContain('Set up nginx')
        ->toContain(cp_route('porro.nginx'));
});

it('blocks an IP, which is then turned away', function () {
    $this->actingAs(admin())
        ->from(cp_route('porro.blocking'))
        ->post(cp_route('porro.block'), ['ip' => '185.220.101.4', 'reason' => 'Scanner', 'days' => 7])
        ->assertRedirect(cp_route('porro.blocking'))
        ->assertSessionHas('success', 'Blocked 185.220.101.4.');

    $row = DB::table('porro_blocklist')->first();

    expect($row->note)->toBe('Scanner')
        ->and($row->added_by)->not->toBeNull()
        ->and((int) $row->expires_at)->toBeGreaterThan(time() + 6 * 86400);

    $this->withServerVariables(['REMOTE_ADDR' => '185.220.101.4'])->get('/')->assertForbidden();
});

it('explains why a block was refused', function () {
    $this->actingAs(admin())
        ->from(cp_route('porro.blocking'))
        ->post(cp_route('porro.block'), ['ip' => '159.203.150.232'])
        ->assertSessionHas('error', fn ($message) => str_contains($message, 'Laravel Forge'));

    expect(DB::table('porro_blocklist')->count())->toBe(0);
});

it('won’t let you block yourself', function () {
    $this->actingAs(admin())
        ->withServerVariables(['REMOTE_ADDR' => '45.148.10.92'])
        ->post(cp_route('porro.block'), ['ip' => '45.148.10.0/24'])
        ->assertSessionHas('error', fn ($message) => str_contains($message, 'your own IP'));
});

it('shows why a block was refused on the page, and keeps it in the right form', function () {
    $this->actingAs(admin())->from(cp_route('porro.blocking'))->post(cp_route('porro.block'), ['form' => 'block', 'ip' => '159.203.150.232']);

    $html = pageHtml($this->actingAs(admin())->get(cp_route('porro.blocking')));

    expect($html)->toContain('role="alert"')->toContain('is a Laravel Forge address')
        ->and(substr_count($html, 'value="159.203.150.232"'))->toBe(1);
});

it('unblocks', function () {
    DB::table('porro_blocklist')->insert((new Entry('185.220.101.4', 'x', Entry::MANUAL, time()))->toArray());

    $this->actingAs(admin())->post(cp_route('porro.unblock'), ['ip' => '185.220.101.4'])->assertSessionHas('success');

    expect(DB::table('porro_blocklist')->count())->toBe(0);
});

it('safelists with a label, and asks before a wide range', function () {
    $this->actingAs(admin())->post(cp_route('porro.safelist'), ['ip' => '5.6.7.8', 'label' => 'CI runner'])->assertSessionHas('success');
    $this->actingAs(admin())->post(cp_route('porro.safelist'), ['ip' => '5.0.0.0/8', 'label' => 'ISP'])->assertSessionHas('error', fn ($m) => str_contains($m, 'Confirm'));
    $this->actingAs(admin())->post(cp_route('porro.safelist'), ['ip' => '5.0.0.0/8', 'label' => 'ISP', 'confirm_wide' => 1])->assertSessionHas('success');

    expect(DB::table('porro_safelist')->orderBy('id')->pluck('cidr')->all())->toBe(['5.6.7.8', '5.0.0.0/8']);
});

it('won’t remove a safelist entry that comes from config', function () {
    config(['porro.blocklist.safelist' => ['81.2.69.142']]);

    $this->actingAs(admin())->post(cp_route('porro.unsafelist'), ['ip' => '81.2.69.142'])
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'config'));
});

it('prefills the safelist form from the activity table', function () {
    expect(pageHtml($this->actingAs(admin())->get(cp_route('porro.blocking').'?safelist=81.2.69.142')))->toContain('value="81.2.69.142"');
});

it('lets people who can view, but not manage, only look', function () {
    $editor = editorWith('view porro site health');

    $html = pageHtml($this->actingAs($editor)->get(cp_route('porro.blocking'))->assertOk());
    expect($html)->not->toContain(cp_route('porro.block'));

    $this->actingAs($editor)->post(cp_route('porro.block'), ['ip' => '185.220.101.4'])->assertForbidden();
    expect(DB::table('porro_blocklist')->count())->toBe(0);

    $this->actingAs(editorWith('access cp'))->get(cp_route('porro.blocking'))->assertForbidden();
});

describe('activity on Site Health', function () {
    beforeEach(function () {
        $recorder = app(Activity::class)->recorder();

        foreach (['/.env', '/.git/config', '/{{ constructor }}.sql'] as $path) {
            $recorder->record('185.220.101.4', Recorder::PROBE, 'GET', $path, 404, country: 'DE');
        }
    });

    it('lists flagged IPs with their country and block buttons', function () {
        $html = pageHtml($this->actingAs(admin())->get(cp_route('porro.index'))->assertOk());

        expect($html)
            ->toContain('Suspicious activity')
            ->toContain('185.220.101.4')
            ->toContain('🇩🇪 DE')
            ->toContain('probe 3')
            ->toContain('Block 7 days')
            ->toContain('1 IP probing for secrets and admin tools')
            // Paths come from visitors: escaped, and out of Vue's reach.
            ->toMatch('/<td v-pre[^>]*>GET \/\{\{ constructor \}\}\.sql<\/td>/');
    });

    it('blocks from the activity table as a suggested, 7-day block', function () {
        $this->actingAs(admin())->post(cp_route('porro.block'), ['ip' => '185.220.101.4', 'reason' => 'Flagged: probe ×3', 'days' => 7, 'suggested' => 1]);

        expect(DB::table('porro_blocklist')->first())->source->toBe('suggested');

        $html = pageHtml($this->actingAs(admin())->get(cp_route('porro.index')));

        // Already blocked: no buttons to block it again.
        expect($html)->toContain('1 IP blocked')->toContain('Blocked')->not->toContain('Block 7 days');
    });

    it('hides the block buttons from people who can’t manage the blocklist', function () {
        $html = pageHtml($this->actingAs(editorWith('view porro site health'))->get(cp_route('porro.index'))->assertOk());

        expect($html)->toContain('185.220.101.4')->not->toContain('Block 7 days');
    });
});

it('shows Cloudflare WAF mode on the Blocking page', function () {
    $page = fn () => pageHtml($this->actingAs(admin())->get(cp_route('porro.blocking'))->assertOk());

    expect($page())->toContain('Cloudflare WAF')->toContain('Not set up')->toContain('Set up Cloudflare')->toContain(cp_route('porro.cloudflare'))->not->toContain('Account Filter Lists');

    config(['porro.blocklist.cloudflare_token' => 'token']);
    expect($page())->toContain('Token set, not connected')->toContain('Finish setting up');

    $this->app['env'] = 'production';
    Cache::forever(CloudflareMode::STATE, [
        'connection' => ['zone_name' => 'dogearbooks.example'],
        'synced_at' => time() - 120,
        'result' => ['added' => 1, 'removed' => 0, 'shared' => 2],
        'error' => null,
    ]);
    expect($page())->toContain('Synced 2 minutes ago')->toContain('zone dogearbooks.example')->toContain('2 already blocked by other sites');

    Cache::forever(CloudflareMode::STATE, [
        'connection' => ['zone_name' => 'dogearbooks.example'],
        'error' => 'Cloudflare refused the token {{ constructor }}',
    ]);
    // The error comes from Cloudflare: escaped, and out of Vue's reach.
    expect($page())->toContain('Sync failed')->toMatch('/<p v-pre[^>]*>Cloudflare refused the token \{\{ constructor \}\}<\/p>/');
});

describe('nginx setup', function () {
    beforeEach(function () {
        $this->dir = sys_get_temp_dir().'/porro-nginx-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/sites-enabled', 0755, true);
        $this->file = $this->dir.'/nginx-blocklist.conf';
        config([
            'porro.blocklist.nginx' => true,
            'porro.blocklist.nginx_path' => $this->file,
            'porro.blocklist.nginx_config_dirs' => [$this->dir.'/sites-enabled'],
            'porro.blocklist.nginx_stamp' => $this->dir.'/stamp',
        ]);
    });

    it('shows the steps with this site’s paths, and each server', function () {
        expect(pageHtml($this->actingAs(admin())->get(cp_route('porro.nginx'))->assertOk()))
            ->toContain('Set up nginx')
            ->toContain("include {$this->file};")
            ->toContain('1 · Turn on nginx mode')
            ->toContain('3 · Reload nginx when the list changes')
            ->toContain('data-porro-copy="include '.$this->file.';"')
            ->toContain('add this line')
            ->toContain('No server has checked in yet');

        $this->app['env'] = 'production';
        file_put_contents($this->dir.'/sites-enabled/site', "server {\n    include {$this->file};\n}\n");
        $this->artisan('porro:blocklist-sync')->assertSuccessful();
        touch($this->dir.'/stamp', time() + 5);
        $this->artisan('porro:blocklist-sync')->assertSuccessful();

        expect(pageHtml($this->actingAs(admin())->get(cp_route('porro.nginx'))))->toContain('Loaded')->toContain('reloaded')
            ->and(pageHtml($this->actingAs(admin())->get(cp_route('porro.blocking'))))->toContain('1 of 1 server loaded')->toContain(cp_route('porro.nginx'));
    });

    it('counts each step done across servers', function () {
        Cache::forever(NginxServers::KEY, [
            'web-1' => ['state' => 'loaded', 'checked_at' => time() - 30, 'reloaded' => time() - 60],
            'web-2' => ['state' => 'waiting_reload', 'checked_at' => time() - 40],
        ]);

        expect(pageHtml($this->actingAs(admin())->get(cp_route('porro.nginx'))))
            ->toContain('Done on 2 of 2 servers')
            ->toContain('Done on 1 of 2 servers');
    });

    it('is for people who can see Site Health', function () {
        $this->actingAs(editorWith('access cp'))->get(cp_route('porro.nginx'))->assertForbidden();
        $this->actingAs(editorWith('view porro site health'))->get(cp_route('porro.nginx'))->assertOk();
    });
});

describe('IP details', function () {
    beforeEach(function () {
        // A fresh fake: the file's catch-all stub would otherwise answer first.
        Http::swap(new Factory);
        Http::fake([
            IpDetails::TOR_URL => Http::response("185.220.101.4\n"),
            'www.spamhaus.org/*' => Http::response(''),
            AbuseIpdb::URL.'*' => Http::response(['data' => ['abuseConfidenceScore' => 100, 'totalReports' => 2048]]),
            '*' => Http::response([], 200),
        ]);
        $this->app->instance('porro.reverse-dns', fn () => null);
        $recorder = app(Activity::class)->recorder();

        foreach (['/.env', '/.git/config', '/{{ constructor }}.sql'] as $path) {
            $recorder->record('185.220.101.4', Recorder::PROBE, 'GET', $path, 404, country: 'DE', userAgent: 'Mozilla/5.0 (compatible; Googlebot/2.1) {{ constructor }}');
        }
    });

    it('opens from the activity table and explains a fake crawler', function () {
        expect(pageHtml($this->actingAs(admin())->get(cp_route('porro.index'))))
            ->toContain(cp_route('porro.ip', '185.220.101.4'))
            // Ranked by what's known: the Tor list marks it in the table.
            ->toMatch('/text="Tor"|badge-pill-sm[^"]*">Tor</');

        $html = pageHtml($this->actingAs(admin())->get(cp_route('porro.ip', '185.220.101.4'))->assertOk());

        expect($html)
            ->toContain('🇩🇪 DE')
            ->toContain('Tor exit')
            ->toContain('Fake Googlebot')
            ->toContain('Pretending to be Googlebot')
            ->toContain('Block permanently')
            // Paths and user agents come from visitors: escaped, and out of Vue's reach.
            ->toMatch('/<span v-pre[^>]*>\/\{\{ constructor \}\}\.sql<\/span>/')
            ->toMatch('/<span v-pre[^>]*>Mozilla\/5\.0 \(compatible; Googlebot\/2\.1\) \{\{ constructor \}\}<\/span>/');
    });

    it('asks in a dialog before every block and safelist, and safelists in one click', function () {
        $url = cp_route('porro.ip', '185.220.101.4');
        $html = pageHtml($this->actingAs(admin())->get($url));

        expect(substr_count($html, 'data-porro-confirm="'))->toBe(3)
            ->and($html)->toContain('<dialog id="porro-confirm"')
            ->toContain('data-porro-ok="danger"')
            ->toContain(e(Confirm::attribute(Confirm::block('185.220.101.4'))))
            ->toContain(e(Confirm::attribute(Confirm::block('185.220.101.4', 7))))
            ->toContain(e(Confirm::attribute(Confirm::safelist('185.220.101.4'))))
            ->and(Statamic::availableInlineScripts(request()))->toContain(Confirm::script());

        $health = pageHtml($this->actingAs(admin())->get(cp_route('porro.index')));
        expect(substr_count($health, 'data-porro-confirm="'))->toBe(3)->and($health)->toContain('<dialog id="porro-confirm"');

        $this->actingAs(admin())->from($url)
            ->post(cp_route('porro.safelist'), ['ip' => '185.220.101.4', 'label' => 'From the IP details page'])
            ->assertRedirect($url);

        expect(pageHtml($this->actingAs(admin())->get($url)))->toContain('safelisted')->not->toContain('data-porro-confirm="');
    });

    it('checks AbuseIPDB only when asked, and only for managers', function () {
        config(['porro.activity.abuseipdb_key' => 'k3y']);
        $url = cp_route('porro.ip', '185.220.101.4');

        expect(pageHtml($this->actingAs(admin())->get($url)))->toContain('Check on AbuseIPDB');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'abuseipdb'));

        $this->actingAs(admin())->from($url)->post(cp_route('porro.reputation', '185.220.101.4'))->assertRedirect($url);
        expect(pageHtml($this->actingAs(admin())->get($url)))->toContain('100%')->toContain('2,048 reports');

        $viewer = editorWith('view porro site health');
        expect(pageHtml($this->actingAs($viewer)->get($url)))->not->toContain('Check on AbuseIPDB')->not->toContain('Block permanently');
        $this->actingAs($viewer)->post(cp_route('porro.reputation', '185.220.101.4'))->assertForbidden();
    });

    it('404s on nonsense and needs the view permission', function () {
        $this->actingAs(admin())->get(cp_route('porro.ip', '1.2.3'))->assertNotFound();
        $this->actingAs(editorWith('access cp'))->get(cp_route('porro.ip', '185.220.101.4'))->assertForbidden();
    });
});

it('opens each always-protected list to show its addresses and why, and marks protected IPs', function () {
    expect(pageHtml($this->actingAs(admin())->get(cp_route('porro.blocking'))))
        ->toContain('Search engine crawlers')
        ->toContain('drop the site from search')
        ->toContain('173.245.48.0/20')
        ->toContain('PORRO_PROTECT_CRAWLERS=false');

    expect(pageHtml($this->actingAs(admin())->get(cp_route('porro.ip', '66.249.66.1'))))
        ->toContain('always protected: Search engine crawlers')
        ->not->toContain('Block permanently');
});

it('hides each Statamic 6 page until Vue has compiled it, so the raw markup never flashes', function () {
    if (! Panel::isStatamic6()) {
        $this->markTestSkipped('Statamic 6 renders addon pages before Vue mounts; 5 hides the whole app.');
    }

    foreach ([cp_route('porro.index'), cp_route('porro.blocking'), cp_route('porro.nginx'), cp_route('porro.ip', '185.220.101.4')] as $url) {
        expect(pageHtml($this->actingAs(admin())->get($url)->assertOk()))->toContain('<div class="max-w-page mx-auto space-y-6" v-cloak>');
    }
});

describe('the full lists', function () {
    beforeEach(function () {
        $rows = [];
        for ($i = 1; $i <= 60; $i++) {
            $rows[] = (new Entry("5.188.10.{$i}", $i % 2 ? 'Probing for .env' : 'Failed logins', $i % 3 ? Entry::SUGGESTED : Entry::MANUAL, 1000 + $i, $i <= 5 ? time() + 3 * 86400 : null, 'Ops'))->toArray();
        }
        DB::table('porro_blocklist')->insert($rows);
    });

    it('shows the newest ten on Blocking, and links to the rest', function () {
        $html = pageHtml($this->actingAs(admin())->get(cp_route('porro.blocking')));

        expect($html)->toContain('Show all 60, with search')->toContain(cp_route('porro.list', 'blocklist'))
            ->and(substr_count($html, 'name="ip" value="5.188.10.'))->toBe(10);
    });

    it('searches, filters and pages the whole list', function () {
        $url = cp_route('porro.list', 'blocklist');

        $first = pageHtml($this->actingAs(admin())->get($url)->assertOk());
        expect($first)->toContain('60 entries')->toContain('Page 1 of 2')
            ->and(substr_count($first, 'name="ip" value='))->toBe(50);

        expect(pageHtml($this->actingAs(admin())->get($url.'?page=2')))->toContain('value="5.188.10.1"')
            ->and(pageHtml($this->actingAs(admin())->get($url.'?q=5.188.10.7')))->toContain('value="5.188.10.7"')->not->toContain('value="5.188.10.8"')
            ->and(substr_count(pageHtml($this->actingAs(admin())->get($url.'?filter=expiring')), 'name="ip" value='))->toBe(5)
            ->and(pageHtml($this->actingAs(admin())->get($url.'?q=nothing-like-this')))->toContain('Nothing matches.');
    });

    it('ignores a query string that isn’t plain values', function () {
        $this->actingAs(admin())->get(cp_route('porro.list', 'blocklist').'?q[]=x&filter[]=manual&page[]=2')->assertOk();
    });

    it('has a safelist page, and is for people who can see Site Health', function () {
        expect(pageHtml($this->actingAs(admin())->get(cp_route('porro.list', 'safelist'))->assertOk()))->toContain('Nothing is safelisted.');
        $this->actingAs(editorWith('access cp'))->get(cp_route('porro.list', 'blocklist'))->assertForbidden();
    });
});

describe('Cloudflare setup', function () {
    beforeEach(function () {
        $this->rules = [];
        $this->app->instance(Cloudflare::class, new Cloudflare('token', function (string $method, string $url, array $headers, ?string $body) {
            $path = (string) parse_url(substr($url, strlen(Cloudflare::API)), PHP_URL_PATH);
            $ok = fn ($result) => [200, json_encode(['success' => true, 'result' => $result, 'result_info' => []])];

            return match (true) {
                $path === '/user/tokens/verify' => $ok(['status' => 'active']),
                $path === '/zones' => $ok(str_contains($url, 'dogearbooks.example') ? [['id' => 'z1', 'name' => 'dogearbooks.example', 'account' => ['id' => 'a1']]] : []),
                $path === '/accounts/a1/rules/lists' && $method === 'GET' => $ok([]),
                $path === '/accounts/a1/rules/lists' => $ok(['id' => 'l1', 'name' => 'porro_blocklist', 'kind' => 'ip']),
                $path === '/accounts/a1/rules/lists/l1/items' && $method === 'GET' => $ok([]),
                str_starts_with($path, '/accounts/a1/rules/lists/') => $ok(['operation_id' => 'op', 'status' => 'completed']),
                str_ends_with($path, '/entrypoint') => $this->rules ? $ok(['id' => 'rs1', 'rules' => $this->rules]) : [404, json_encode(['success' => false, 'errors' => [['message' => 'not found']]])],
                $path === '/zones/z1/rulesets' => $ok(['id' => 'rs1', 'rules' => $this->rules = [['id' => 'r1'] + json_decode((string) $body, true)['rules'][0]]]),
                default => [404, json_encode(['success' => false, 'errors' => [['message' => "no route {$method} {$path}"]]])],
            };
        }, fn () => null));
        config(['app.url' => 'https://www.dogearbooks.example']);
    });

    it('walks through the token, the environment and connecting', function () {
        $html = pageHtml($this->actingAs(admin())->get(cp_route('porro.cloudflare'))->assertOk());

        expect($html)->toContain('1 · Create a Cloudflare API token')
            ->toContain('2 · Add it to the site’s environment')
            ->toContain('3 · Connect')
            ->toContain('Zone WAF Rules')
            ->toContain('Account Rule Lists')
            ->toContain('Specified Domains → www.dogearbooks.example')
            ->toContain('data-porro-copy="PORRO_CLOUDFLARE_TOKEN="')
            ->toContain('One list for the whole Cloudflare account.')
            ->toContain('php please porro:cloudflare connect')
            ->toContain(cp_route('porro.cloudflare.connect'));
    });

    it('connects from the page and syncs', function () {
        config(['porro.blocklist.cloudflare_token' => 'token']);
        $this->app['env'] = 'production';

        // Production checks the CSRF token.
        $this->actingAs(admin())->withSession(['_token' => 't'])->from(cp_route('porro.cloudflare'))->post(cp_route('porro.cloudflare.connect'), ['_token' => 't'])
            ->assertRedirect(cp_route('porro.cloudflare'))
            ->assertSessionHas('success', 'Connected to dogearbooks.example and synced the blocklist.');

        expect($this->rules)->toHaveCount(1)
            ->and(pageHtml($this->actingAs(admin())->get(cp_route('porro.cloudflare'))))->toContain('Connected to dogearbooks.example');
    });

    it('asks for the token before connecting', function () {
        $this->actingAs(admin())->from(cp_route('porro.cloudflare'))->post(cp_route('porro.cloudflare.connect'))
            ->assertSessionHas('error', 'Set PORRO_CLOUDFLARE_TOKEN first, then connect.');

        expect($this->rules)->toBe([]);
    });

    it('lets only users who manage blocking connect', function () {
        config(['porro.blocklist.cloudflare_token' => 'token']);
        $viewer = editorWith(Panel::VIEW);

        expect(pageHtml($this->actingAs($viewer)->get(cp_route('porro.cloudflare'))->assertOk()))->not->toContain('Connect to Cloudflare');
        $this->actingAs($viewer)->post(cp_route('porro.cloudflare.connect'))->assertForbidden();

        expect($this->rules)->toBe([]);
    });
});
