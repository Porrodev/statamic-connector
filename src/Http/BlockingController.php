<?php

namespace Porro\Statamic\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Porro\Core\Activity\Blocklist;
use Porro\Core\Activity\CloudflareError;
use Porro\Core\Activity\Entry;
use Porro\Core\Activity\Refused;
use Porro\Laravel\Activity\Activity;
use Porro\Laravel\Activity\CloudflareMode;
use Porro\Laravel\SiteHealth;
use Porro\Statamic\Panel;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use Throwable;

/**
 * The blocklist and safelist. Every rule about what may be blocked lives
 * in core's Blocklist; this only turns its refusals into messages.
 */
class BlockingController extends CpController
{
    public function index(Request $request, Panel $panel): View
    {
        Panel::authorize(Panel::VIEW);

        return view('porro::blocking', $panel->blocking($request));
    }

    /** The whole blocklist or safelist: searchable, filterable, 50 a page. */
    public function list(Request $request, Panel $panel, string $list): View
    {
        Panel::authorize(Panel::VIEW);

        return view('porro::list', $panel->list($request, $list));
    }

    public function nginx(Request $request, Panel $panel): View
    {
        Panel::authorize(Panel::VIEW);

        return view('porro::nginx', $panel->nginx($request));
    }

    public function cloudflare(Panel $panel): View
    {
        Panel::authorize(Panel::VIEW);

        return view('porro::cloudflare', $panel->cloudflare());
    }

    /**
     * Connect from the page, so nobody needs a terminal: finds the zone,
     * creates or reuses the list and adds the rule, then syncs.
     */
    public function cloudflareConnect(CloudflareMode $cloudflare): RedirectResponse
    {
        Panel::authorize(Panel::MANAGE);

        if (! $cloudflare->configured()) {
            return back()->with('error', __('Set PORRO_CLOUDFLARE_TOKEN first, then connect.'));
        }

        try {
            $connection = $cloudflare->connect();
        } catch (CloudflareError $e) {
            return back()->with('error', $e->getMessage());
        }

        $result = $cloudflare->sync();

        return back()->with('success', $result !== null
            ? __('Connected to :zone and synced the blocklist.', ['zone' => $connection['zone_name']])
            : __('Connected to :zone. Only production syncs the blocklist.', ['zone' => $connection['zone_name']]));
    }

    public function block(Request $request, Activity $activity): RedirectResponse
    {
        Panel::authorize(Panel::MANAGE);

        $data = $request->validate([
            'ip' => ['required', 'string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:255'],
            'days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'suggested' => ['nullable', 'boolean'],
        ]);

        $days = (int) ($data['days'] ?? 0);

        return $this->change($activity, fn (Blocklist $list) => $list->block(
            $data['ip'],
            ($data['reason'] ?? '') ?: __('Blocked in the control panel'),
            ($data['suggested'] ?? false) ? Entry::SUGGESTED : Entry::MANUAL,
            $days > 0 ? time() + $days * 86400 : null,
            $this->who(),
            $activity->clientIp($request),
        ), fn (Entry $entry) => __('Blocked :ip.', ['ip' => $entry->cidr]));
    }

    public function unblock(Request $request, Activity $activity): RedirectResponse
    {
        Panel::authorize(Panel::MANAGE);

        $ip = (string) $request->validate(['ip' => ['required', 'string', 'max:64']])['ip'];

        return $this->change($activity, fn (Blocklist $list) => $list->unblock($ip), fn () => __('Unblocked :ip.', ['ip' => $ip]));
    }

    public function safelist(Request $request, Activity $activity): RedirectResponse
    {
        Panel::authorize(Panel::MANAGE);

        $data = $request->validate([
            'ip' => ['required', 'string', 'max:64'],
            'label' => ['nullable', 'string', 'max:255'],
            'hours' => ['nullable', 'integer', 'min:0', 'max:87600'],
            'confirm_wide' => ['nullable', 'boolean'],
        ]);

        $hours = (int) ($data['hours'] ?? 0);

        return $this->change($activity, fn (Blocklist $list) => $list->safelist(
            $data['ip'],
            (string) ($data['label'] ?? ''),
            $hours > 0 ? time() + $hours * 3600 : null,
            $this->who(),
            (bool) ($data['confirm_wide'] ?? false),
        ), fn (Entry $entry) => __('Safelisted :ip.', ['ip' => $entry->cidr]));
    }

    public function unsafelist(Request $request, Activity $activity): RedirectResponse
    {
        Panel::authorize(Panel::MANAGE);

        $ip = (string) $request->validate(['ip' => ['required', 'string', 'max:64']])['ip'];

        return $this->change($activity, fn (Blocklist $list) => $list->unsafelist($ip), fn () => __('Removed :ip from the safelist.', ['ip' => $ip]));
    }

    /**
     * Applies a change, then refreshes everything that depends on the
     * lists: the request-time copy, the nginx file and the panel.
     */
    private function change(Activity $activity, \Closure $change, \Closure $message): RedirectResponse
    {
        try {
            $result = $change($activity->blocklist());
        } catch (Refused $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $activity->changed();

        try {
            if (config('porro.blocklist.nginx')) {
                Artisan::call('porro:blocklist-sync');
            }

            app(SiteHealth::class)->forget();
        } catch (Throwable $e) {
            report($e);
        }

        return back()->with('success', $message($result));
    }

    private function who(): ?string
    {
        $user = User::current();

        return $user ? ($user->name() ?: $user->email()) : null;
    }
}
