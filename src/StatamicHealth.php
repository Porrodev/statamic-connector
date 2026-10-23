<?php

namespace Porro\Statamic;

use Facades\Statamic\Updater\UpdatesOverview;
use Porro\Core\Health\Issue;
use Porro\Core\Health\SiteHealth;
use Porro\Core\Report;
use Throwable;

/**
 * Statamic's own checks for the Site health panel: its support dates, and
 * the updates Statamic's updater already knows about.
 */
class StatamicHealth
{
    public function check(SiteHealth $health): void
    {
        $health->endOfLife(['statamic' => Report::installedVersion('statamic/cms')]);

        try {
            $count = (int) UpdatesOverview::count();
            $statamic = (bool) UpdatesOverview::hasStatamicUpdate();
            $addons = count((array) UpdatesOverview::updatableAddons());
            // Statamic 6 flags security releases; 5 doesn't.
            $security = method_exists(\Statamic\Updater\UpdatesOverview::class, 'hasSecurityUpdate') && UpdatesOverview::hasSecurityUpdate();
        } catch (Throwable) {
            $health->unavailable(__('Couldn’t check for Statamic updates'));

            return;
        }

        $health->add($count > 0 ? new Issue(
            'cms_update',
            $security ? Issue::WARNING : Issue::NOTICE,
            $security
                ? __('Security update available')
                : trans_choice(':count update available|:count updates available', $count, ['count' => $count]),
            implode(' · ', array_filter([
                $statamic ? 'Statamic' : null,
                $addons ? trans_choice(':count addon|:count addons', $addons, ['count' => $addons]) : null,
            ])),
            null,
            cp_route('updater'),
        ) : null, __('Statamic and addon updates'));
    }
}
