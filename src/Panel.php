<?php

namespace Porro\Statamic;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Porro\Core\Health\Issue;
use Porro\Core\Report;
use Porro\Laravel\Activity\Presenter;
use Porro\Laravel\SiteHealth;
use Statamic\Facades\User;
use Throwable;

/**
 * View data for the Site Health and Blocking pages and the widget.
 */
class Panel
{
    // The Porro mark, so the panel always reads as Porro's.
    public const ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 1.5 18.5 10 10 18.5 1.5 10Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="10" cy="10" r="2.5" fill="currentColor"/></svg>';

    public const BLOCK_ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.6"/><path d="m4.7 4.7 10.6 10.6" stroke="currentColor" stroke-width="1.6"/></svg>';

    public const VIEW = 'view porro site health';

    public const MANAGE = 'manage porro blocklist';

    public const SEVERITIES = [
        Issue::CRITICAL => ['label' => 'Act now', 'color' => 'red', 'hex' => '#dc2626'],
        Issue::WARNING => ['label' => 'Needs attention', 'color' => 'amber', 'hex' => '#d97706'],
        Issue::NOTICE => ['label' => 'Notice', 'color' => 'default', 'hex' => '#6b7280'],
    ];

    private const COMMANDS = ['connect' => 'php please porro:connect <site key>', 'cron' => 'php please porro:cron'];

    public function __construct(private SiteHealth $health, private Presenter $presenter) {}

    public static function can(string $permission): bool
    {
        $user = User::current();

        return $user !== null && ($user->isSuper() || $user->hasPermission($permission));
    }

    public static function authorize(string $permission): void
    {
        abort_unless(self::can($permission), 403);
    }

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
            'flash' => self::flash(),
            'utilityUrl' => cp_route('porro.index'),
            'refreshUrl' => cp_route('porro.refresh'),
            'activity' => $this->presenter->activity($health['activity'] ?? null),
            'canManage' => self::can(self::MANAGE),
            'blockingUrl' => cp_route('porro.blocking'),
            'blockUrl' => cp_route('porro.block'),
            'safelistUrl' => cp_route('porro.safelist'),
            'v6' => self::isStatamic6(),
            'icon' => self::ICON,
            'severities' => self::SEVERITIES,
        ];
    }

    /**
     * The Blocking page.
     *
     * @return array<string, mixed>
     */
    public function blocking(Request $request): array
    {
        return [
            'flash' => self::flash(),
            'v6' => self::isStatamic6(),
            'icon' => self::BLOCK_ICON,
            'canManage' => self::can(self::MANAGE),
            'healthUrl' => cp_route('porro.index'),
            'nginxUrl' => cp_route('porro.nginx'),
            'blocklistUrl' => cp_route('porro.list', 'blocklist'),
            'safelistUrl' => cp_route('porro.list', 'safelist'),
            'urls' => [
                'block' => cp_route('porro.block'),
                'unblock' => cp_route('porro.unblock'),
                'safelist' => cp_route('porro.safelist'),
                'unsafelist' => cp_route('porro.unsafelist'),
            ],
            'prefill' => filter_var($request->query('safelist'), FILTER_VALIDATE_IP) ?: null,
            ...$this->presenter->blocking(),
        ];
    }

    /**
     * The whole blocklist or safelist.
     *
     * @return array<string, mixed>
     */
    public function list(Request $request, string $list): array
    {
        $entries = $this->presenter->entries($list, (string) $request->query('q', ''), (string) $request->query('filter', 'all'), (int) $request->query('page', 1));

        // Statamic's layout keeps its Inertia page data in $page.
        $entries['currentPage'] = $entries['page'];
        unset($entries['page']);

        return [
            'flash' => self::flash(),
            'v6' => self::isStatamic6(),
            'icon' => self::BLOCK_ICON,
            'canManage' => self::can(self::MANAGE),
            'blockingUrl' => cp_route('porro.blocking'),
            'listUrl' => cp_route('porro.list', $list),
            'remove' => cp_route($list === 'safelist' ? 'porro.unsafelist' : 'porro.unblock'),
            ...$entries,
        ];
    }

    /**
     * IP details.
     *
     * @return array<string, mixed>
     */
    public function ip(Request $request, string $ip): array
    {
        $reputation = session('reputation');

        return [
            'v6' => self::isStatamic6(),
            'icon' => self::BLOCK_ICON,
            'flash' => self::flash(),
            'canManage' => self::can(self::MANAGE),
            'healthUrl' => cp_route('porro.index'),
            'blockingUrl' => cp_route('porro.blocking'),
            'blockUrl' => cp_route('porro.block'),
            'safelistUrl' => cp_route('porro.safelist'),
            'reputationUrl' => cp_route('porro.reputation', $ip),
            'details' => $this->presenter->ip($ip, is_array($reputation) && ($reputation['ip'] ?? null) === $ip ? $reputation : null),
        ];
    }

    /**
     * The nginx setup page.
     *
     * @return array<string, mixed>
     */
    public function nginx(Request $request): array
    {
        return [
            'v6' => self::isStatamic6(),
            'icon' => self::BLOCK_ICON,
            'blockingUrl' => cp_route('porro.blocking'),
            ...$this->presenter->nginx((string) $request->server('REMOTE_ADDR')),
        ];
    }

    /**
     * The last action's outcome, shown on the page itself: Statamic 6
     * doesn't toast flashed messages on Blade pages.
     *
     * @return array{error: string|null, success: string|null}
     */
    private static function flash(): array
    {
        return [
            'error' => is_string(session('error')) ? session('error') : null,
            'success' => is_string(session('success')) ? session('success') : null,
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
            'activity' => is_array($health['activity'] ?? null) ? $health['activity'] : null,
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
