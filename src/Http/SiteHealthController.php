<?php

namespace Porro\Statamic\Http;

use Illuminate\Http\RedirectResponse;
use Porro\Laravel\SiteHealth;
use Statamic\Http\Controllers\CP\CpController;

class SiteHealthController extends CpController
{
    /**
     * "Check again": run every check now rather than use the cached result.
     * Behind the utility's own permission (see the service provider).
     */
    public function refresh(SiteHealth $health): RedirectResponse
    {
        $health->get(fresh: true);

        return redirect(cp_route('utilities.index').'/porro')->with('success', __('Checked again.'));
    }
}
