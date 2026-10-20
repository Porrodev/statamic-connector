<?php

namespace Porro\Statamic;

use Porro\Core\Report;
use Porro\Laravel\Collector;
use Statamic\Facades\Addon;
use Statamic\Statamic;
use Throwable;

/**
 * Statamic version and edition, installed addons, and the settings that
 * matter on a live site.
 */
class StatamicCollector implements Collector
{
    public function collect(Report $report): void
    {
        $report->layer('statamic', Report::installedVersion('statamic/cms'), [
            'pro' => $this->pro(),
            'addons' => $this->addons(),
            'static_caching' => config('statamic.static_caching.strategy'),
            'git' => (bool) config('statamic.git.enabled'),
            'stache_watcher' => config('statamic.stache.watcher'),
        ]);
    }

    private function pro(): ?bool
    {
        try {
            return Statamic::pro();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array{name: string, version: string|null}>
     */
    private function addons(): array
    {
        try {
            return Addon::all()
                ->map(fn ($addon) => ['name' => (string) $addon->package(), 'version' => $addon->version()])
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
