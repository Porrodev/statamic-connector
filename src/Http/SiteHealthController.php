<?php

namespace Porro\Statamic\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Porro\Laravel\SiteHealth;
use Porro\Statamic\Panel;
use Statamic\Http\Controllers\CP\CpController;

class SiteHealthController extends CpController
{
    public function index(Panel $panel): View
    {
        Panel::authorize(Panel::VIEW);

        return view('porro::site-health', $panel->data());
    }

    /**
     * "Check again": run every check now rather than use the cached result.
     */
    public function refresh(SiteHealth $health): RedirectResponse
    {
        Panel::authorize(Panel::VIEW);

        $health->get(fresh: true);

        return redirect(cp_route('porro.index'))->with('success', __('Checked again.'));
    }
}
