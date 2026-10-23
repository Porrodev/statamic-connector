<?php

namespace Porro\Statamic;

use Carbon\Carbon;
use Porro\Core\Health\Issue;
use Porro\Core\Report;
use Porro\Laravel\SiteHealth;
use Throwable;

/**
 * The Site health utility's and widget's view data.
 */
class Panel
{
    public const ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7.5-4.35-9.3-9.2C1.4 8.2 3.6 4.5 7.3 4.5c2 0 3.5 1.1 4.7 2.7 1.2-1.6 2.7-2.7 4.7-2.7 3.7 0 5.9 3.7 4.6 7.3C19.5 16.65 12 21 12 21z"/><path d="M3.5 12h4l1.5-3 3 6 1.5-3h7"/></svg>';

    public const SEVERITIES = [
        Issue::CRITICAL => ['label' => 'Act now', 'color' => 'red', 'hex' => '#dc2626'],
        Issue::WARNING => ['label' => 'Needs attention', 'color' => 'amber', 'hex' => '#d97706'],
        Issue::NOTICE => ['label' => 'Notice', 'color' => 'default', 'hex' => '#6b7280'],
    ];

    public function __construct(private SiteHealth $health) {}

    /**
     * @param  int|null  $limit  how many issues to list, for the widget
     * @param  bool  $cachedOnly  never run the checks, so a dashboard never waits on the network
     * @return array<string, mixed>
     */
    public function data(?int $limit = null, bool $cachedOnly = false): array
    {
        try {
            $health = $cachedOnly ? $this->health->cached() : $this->health->get();
        } catch (Throwable $e) {
            report($e);
            $health = null;
        }

        $porro = $health['porro'] ?? null;
        $local = $this->present($health['issues'] ?? []);
        $found = $this->present($porro['findings'] ?? []);
        $all = Issue::sort(array_map(fn ($i) => Issue::fromArray($i), [...($porro['findings'] ?? []), ...($health['issues'] ?? [])]));

        return [
            'failed' => $health === null && ! $cachedOnly,
            'pending' => $health === null && $cachedOnly,
            'connected' => (bool) ($health['connected'] ?? false),
            'enabled' => (bool) ($health['enabled'] ?? true),
            'porro' => $porro,
            'porroOk' => (bool) ($porro['ok'] ?? false),
            'porroUrl' => $porro['site_url'] ?? null,
            'local' => $local,
            'found' => $found,
            'top' => $this->present(array_map(fn (Issue $i) => $i->toArray(), $limit ? array_slice($all, 0, $limit) : $all)),
            'more' => $limit ? max(0, count($all) - $limit) : 0,
            'counts' => $health['counts'] ?? ['critical' => 0, 'warning' => 0, 'notice' => 0],
            'checked' => $health['checked'] ?? [],
            'unavailable' => $health['unavailable'] ?? [],
            'checkedAgo' => self::ago($health['checked_at'] ?? null),
            'commands' => $health['commands'] ?? ['connect' => 'php please porro:connect <site key>', 'cron' => 'php please porro:cron'],
            'utilityUrl' => cp_route('utilities.index').'/porro',
            'refreshUrl' => cp_route('utilities.porro.refresh'),
            'v6' => self::isStatamic6(),
            'icon' => self::ICON,
            'severities' => self::SEVERITIES,
        ];
    }

    public static function isStatamic6(): bool
    {
        return version_compare(ltrim((string) Report::installedVersion('statamic/cms'), 'v'), '6.0.0-alpha', '>=');
    }

    private static function ago(?string $at): ?string
    {
        if (! $at) {
            return null;
        }

        $at = Carbon::parse($at);

        return $at->gt(now()->subMinute()) ? __('just now') : $at->diffForHumans();
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     * @return list<array<string, mixed>>
     */
    private function present(array $issues): array
    {
        return array_map(fn (array $issue) => $issue + ['style' => self::SEVERITIES[$issue['severity']] ?? self::SEVERITIES[Issue::NOTICE]], $issues);
    }
}
