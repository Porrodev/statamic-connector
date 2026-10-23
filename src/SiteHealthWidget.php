<?php

namespace Porro\Statamic;

use Statamic\Facades\User;
use Statamic\Widgets\Widget;

/**
 * Dashboard: what needs attention, linking to Utilities → Site health.
 * Shows the last result only, so the dashboard never waits on checks.
 *
 *     // config/statamic/cp.php
 *     'widgets' => [
 *         ['type' => 'porro_site_health', 'width' => 50],
 *     ],
 */
class SiteHealthWidget extends Widget
{
    protected static $handle = 'porro_site_health';

    public function html()
    {
        if (! User::current()?->can('access porro utility')) {
            return '';
        }

        return view('porro::widget', app(Panel::class)->data((int) $this->config('limit', 5), cachedOnly: true));
    }
}
