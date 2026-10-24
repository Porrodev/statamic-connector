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
    // The Porro mark, so the panel always reads as Porro's.
    public const ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 1.5 18.5 10 10 18.5 1.5 10Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="10" cy="10" r="2.5" fill="currentColor"/></svg>';

    public const SEVERITIES = [
        Issue::CRITICAL => ['label' => 'Act now', 'color' => 'red', 'hex' => '#dc2626'],
        Issue::WARNING => ['label' => 'Needs attention', 'color' => 'amber', 'hex' => '#d97706'],
        Issue::NOTICE => ['label' => 'Notice', 'color' => 'default', 'hex' => '#6b7280'],
    ];

    private const COMMANDS = ['connect' => 'php please porro:connect <site key>', 'cron' => 'php please porro:cron'];

    public function __construct(private SiteHealth $health) {}

    /**
     * @param  int|null  $limit  how many issues to list, for the widget
     * @param  bool  $cachedOnly  never run the checks, so a dashboard never waits on the network
     * @return array<string, mixed>
     */
    public function data(?int $limit = null, bool $cachedOnly = false): array
    {
        // Anything odd in the cached or fetched result shows the
        // "unavailable" state rather than breaking the page.
        try {
            $health = $cachedOnly ? $this->health->cached() : $this->health->get();
            $health = is_array($health) ? self::normalise($health) : null;
        } catch (Throwable $e) {
            report($e);
            $health = null;
        }

        $porro = $health['porro'] ?? null;
        $all = Issue::sort(array_map(fn ($i) => Issue::fromArray($i), [...($porro['findings'] ?? []), ...($health['issues'] ?? [])]));

        return [
            'failed' => $health === null && ! $cachedOnly,
            'pending' => $health === null && $cachedOnly,
            'connected' => $health['connected'] ?? false,
            'enabled' => $health['enabled'] ?? true,
            'porro' => $porro,
            'porroOk' => $porro['ok'] ?? false,
            'porroUrl' => $porro['site_url'] ?? null,
            'local' => $this->present($health['issues'] ?? []),
            'found' => $this->present($porro['findings'] ?? []),
            'top' => $this->present(array_map(fn (Issue $i) => $i->toArray(), $limit ? array_slice($all, 0, $limit) : $all)),
            'more' => $limit ? max(0, count($all) - $limit) : 0,
            'counts' => $health['counts'] ?? ['critical' => 0, 'warning' => 0, 'notice' => 0],
            'checked' => $health['checked'] ?? [],
            'unavailable' => $health['unavailable'] ?? [],
            'checkedAgo' => self::ago($health['checked_at'] ?? null),
            'commands' => $health['commands'] ?? self::COMMANDS,
            'utilityUrl' => cp_route('utilities.index').'/porro',
            'refreshUrl' => cp_route('utilities.porro.refresh'),
            'v6' => self::isStatamic6(),
            'icon' => self::ICON,
            'severities' => self::SEVERITIES,
        ];
    }

    /**
     * The health result with every field the views use in the shape they
     * expect. It comes from the cache, and in part from Porro.
     *
     * @param  array<mixed>  $health
     * @return array{connected: bool, enabled: bool, porro: array{ok: bool, findings: list<array<string, mixed>>, site_url: string|null, error: string|null}|null, issues: list<array<string, mixed>>, counts: array{critical: int, warning: int, notice: int}, checked: list<string>, unavailable: list<string>, checked_at: string|null, commands: array{connect: string, cron: string}}
     */
    public static function normalise(array $health): array
    {
        $porro = $health['porro'] ?? null;
        $counts = is_array($health['counts'] ?? null) ? $health['counts'] : [];
        $commands = is_array($health['commands'] ?? null) ? $health['commands'] : [];

        return [
            'connected' => (bool) ($health['connected'] ?? false),
            'enabled' => (bool) ($health['enabled'] ?? true),
            'porro' => is_array($porro) ? [
                'ok' => ($porro['ok'] ?? false) === true,
                'findings' => self::issues($porro['findings'] ?? []),
                'site_url' => self::link($porro['site_url'] ?? null),
                'error' => is_string($porro['error'] ?? null) ? $porro['error'] : null,
            ] : null,
            'issues' => self::issues($health['issues'] ?? []),
            'counts' => array_map(fn ($severity) => is_numeric($counts[$severity] ?? null) ? max(0, (int) $counts[$severity]) : 0, [
                'critical' => Issue::CRITICAL, 'warning' => Issue::WARNING, 'notice' => Issue::NOTICE,
            ]),
            'checked' => self::strings($health['checked'] ?? []),
            'unavailable' => self::strings($health['unavailable'] ?? []),
            'checked_at' => is_string($health['checked_at'] ?? null) && strtotime($health['checked_at']) !== false ? $health['checked_at'] : null,
            'commands' => [
                'connect' => is_string($commands['connect'] ?? null) ? $commands['connect'] : self::COMMANDS['connect'],
                'cron' => is_string($commands['cron'] ?? null) ? $commands['cron'] : self::COMMANDS['cron'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function issues(mixed $issues): array
    {
        if (! is_array($issues)) {
            return [];
        }

        $valid = array_filter($issues, fn ($issue) => is_array($issue) && is_scalar($issue['title'] ?? null) && (string) $issue['title'] !== '');

        return array_values(array_map(function (array $issue) {
            $issue = Issue::fromArray(array_map(fn ($value) => is_scalar($value) || $value === null ? $value : null, $issue))->toArray();

            return array_merge($issue, ['url' => self::link($issue['url'])]);
        }, $valid));
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $values): array
    {
        return is_array($values) ? array_values(array_map('strval', array_filter($values, 'is_scalar'))) : [];
    }

    /**
     * Only web links become links in the panel.
     */
    private static function link(mixed $url): ?string
    {
        return is_string($url) && preg_match('#^https?://#i', $url) ? $url : null;
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
        return array_map(fn (array $issue) => array_merge($issue, ['style' => self::SEVERITIES[$issue['severity'] ?? null] ?? self::SEVERITIES[Issue::NOTICE]]), $issues);
    }
}
