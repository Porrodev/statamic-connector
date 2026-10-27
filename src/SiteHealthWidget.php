<?php

namespace Porro\Statamic;

use Statamic\Widgets\Widget;

/**
 * Dashboard: what needs attention, linking to Porro → Site Health.
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
        if (! Panel::can(Panel::VIEW)) {
            return '';
        }

        return view('porro::widget', app(Panel::class)->data((int) $this->config('limit', 5), cachedOnly: true));
    }
}
