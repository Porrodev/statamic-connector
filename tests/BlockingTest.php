<?php

use Facades\Statamic\Updater\UpdatesOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Porro\Core\Activity\Entry;
use Porro\Core\Activity\Recorder;
use Porro\Laravel\Activity\Activity;
use Statamic\Facades\Role;
use Statamic\Facades\User;

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
        ->toContain('PORRO_BLOCK_NGINX=true');
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
