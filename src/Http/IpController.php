<?php

namespace Porro\Statamic\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Porro\Laravel\Activity\Activity;
use Porro\Statamic\Panel;
use Statamic\Http\Controllers\CP\CpController;

/**
 * IP details: who's behind a flagged IP, what it did here, and what can
 * be done. Blocking goes through BlockingController like everywhere else.
 */
class IpController extends CpController
{
    public function show(Request $request, Panel $panel, string $ip): View
    {
        Panel::authorize(Panel::VIEW);
        abort_unless(filter_var($ip, FILTER_VALIDATE_IP) !== false, 404);

        return view('porro::ip', $panel->ip($request, $ip));
    }

    /**
     * AbuseIPDB, on request only: it's the one lookup that sends the IP to
     * a third party, so it needs the manage permission too.
     */
    public function reputation(Activity $activity, string $ip): RedirectResponse
    {
        Panel::authorize(Panel::MANAGE);
        abort_unless(filter_var($ip, FILTER_VALIDATE_IP) !== false, 404);

        $result = $activity->reputation($ip);

        return $result === null
            ? back()->with('error', __('AbuseIPDB couldn’t be reached just now, or the key was refused.'))
            : back()->with('reputation', $result + ['ip' => $ip]);
    }
}
