<?php

namespace Porro\Statamic;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Porro\Core\Activity\Entry;
use Porro\Core\Activity\NginxBlocklist;
use Porro\Core\Activity\ProtectedRanges;
use Porro\Core\Activity\Recorder;
use Porro\Core\Health\Issue;
use Porro\Core\Report;
use Porro\Laravel\Activity\Activity;
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

    /** How each signal reads on a chip. */
    public const SIGNALS = [Recorder::PROBE => 'probe', Recorder::LOGIN_FAILED => 'login', Recorder::NOT_FOUND => '404', Recorder::POST_FLOOD => 'form'];

    public const SEVERITIES = [
        Issue::CRITICAL => ['label' => 'Act now', 'color' => 'red', 'hex' => '#dc2626'],
        Issue::WARNING => ['label' => 'Needs attention', 'color' => 'amber', 'hex' => '#d97706'],
        Issue::NOTICE => ['label' => 'Notice', 'color' => 'default', 'hex' => '#6b7280'],
    ];

    private const COMMANDS = ['connect' => 'php please porro:connect <site key>', 'cron' => 'php please porro:cron'];

    public function __construct(private SiteHealth $health, private Activity $activity) {}

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
            'activity' => $this->activity($health['activity'] ?? null),
            'canManage' => self::can(self::MANAGE),
            'blockingUrl' => cp_route('porro.blocking'),
            'blockUrl' => cp_route('porro.block'),
            'v6' => self::isStatamic6(),
            'icon' => self::ICON,
            'severities' => self::SEVERITIES,
        ];
    }

    /**
     * The Activity section: totals, and each flagged IP ready to show.
     *
     * @return array<string, mixed>|null
     */
    private function activity(mixed $activity): ?array
    {
        if (! is_array($activity)) {
            return null;
        }

        $flagged = [];

        foreach (is_array($activity['flagged'] ?? null) ? $activity['flagged'] : [] as $ip) {
            if (! is_array($ip) || ! is_string($ip['ip'] ?? null) || filter_var($ip['ip'], FILTER_VALIDATE_IP) === false) {
                continue;
            }

            $signals = [];

            foreach (self::SIGNALS as $signal => $label) {
                if (($n = (int) ($ip['counts'][$signal] ?? 0)) > 0) {
                    $signals[] = ['label' => $label, 'count' => $n];
                }
            }

            usort($signals, fn ($a, $b) => $b['count'] <=> $a['count']);

            $flagged[] = [
                'ip' => $ip['ip'],
                'country' => self::country($ip['country'] ?? null),
                'signals' => $signals,
                'request' => trim(((string) ($ip['last_method'] ?? '')).' '.((string) ($ip['last_path'] ?? ''))),
                'seen' => isset($ip['last_seen']) ? Carbon::createFromTimestamp((int) $ip['last_seen'])->diffForHumans() : null,
                'blocked' => $this->isBlocked($ip['ip']),
                'reason' => __('Flagged: :signals', ['signals' => implode(', ', array_map(fn ($s) => "{$s['label']} ×{$s['count']}", $signals))]),
            ];
        }

        $totals = is_array($activity['totals'] ?? null) ? $activity['totals'] : [];

        return [
            'recording' => (bool) ($activity['recording'] ?? false),
            'hours' => (int) ($activity['hours'] ?? 24),
            'totals' => [
                ['label' => __('Probes for secrets and admin tools'), 'value' => (int) ($totals[Recorder::PROBE] ?? 0)],
                ['label' => __('Failed logins'), 'value' => (int) ($totals[Recorder::LOGIN_FAILED] ?? 0)],
                ['label' => __('404s'), 'value' => (int) ($totals[Recorder::NOT_FOUND] ?? 0)],
                ['label' => __('Form posts'), 'value' => (int) ($totals[Recorder::POST_FLOOD] ?? 0)],
            ],
            'flagged' => $flagged,
            'blocked' => (int) ($activity['blocked'] ?? 0),
            'safelisted' => (int) ($activity['safelisted'] ?? 0),
            'countries' => is_array($activity['countries'] ?? null) ? $activity['countries'] : null,
        ];
    }

    private function isBlocked(string $ip): bool
    {
        try {
            return $this->activity->blocked($ip) !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The Blocking page.
     *
     * @return array<string, mixed>
     */
    public function blocking(Request $request): array
    {
        $now = time();
        $list = $this->activity->blocklist();

        try {
            $blocks = $list->blocks($now);
            $safe = $list->safe($now);
            $failed = false;
        } catch (Throwable $e) {
            report($e);
            [$blocks, $safe, $failed] = [[], [], true];
        }

        $ranges = $this->activity->ranges()->ranges();
        $nginxPath = (string) config('porro.blocklist.nginx_path');

        return [
            'failed' => $failed,
            'flash' => self::flash(),
            'v6' => self::isStatamic6(),
            'icon' => self::BLOCK_ICON,
            'canManage' => self::can(self::MANAGE),
            'healthUrl' => cp_route('porro.index'),
            'urls' => [
                'block' => cp_route('porro.block'),
                'unblock' => cp_route('porro.unblock'),
                'safelist' => cp_route('porro.safelist'),
                'unsafelist' => cp_route('porro.unsafelist'),
            ],
            'prefill' => filter_var($request->query('safelist'), FILTER_VALIDATE_IP) ?: null,
            'blocks' => array_map(fn (Entry $e) => $this->entry($e, $now), $blocks),
            'safe' => array_map(fn (Entry $e) => $this->entry($e, $now), $safe),
            'enforcement' => [
                'app' => $this->activity->enforcing(),
                'nginx' => (bool) config('porro.blocklist.nginx'),
                'production' => $this->activity->isProduction(),
                'nginxPath' => $nginxPath,
                'nginxWritten' => is_file($nginxPath) ? Carbon::createFromTimestamp((int) filemtime($nginxPath))->diffForHumans() : null,
                'include' => "include {$nginxPath};",
                'realIp' => NginxBlocklist::realIp($this->activity->ranges()),
                'reload' => sprintf('f=%s; s=/var/tmp/porro-nginx.stamp; [ "$f" -nt "$s" ] && nginx -t -q && systemctl reload nginx && touch "$s"', $nginxPath),
            ],
            'protected' => array_values(array_filter(array_map(fn (string $source) => [
                'label' => ProtectedRanges::label($source),
                'count' => count($ranges[$source] ?? []),
            ], array_keys($ranges)), fn ($row) => $row['count'] > 0)),
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
     * @return array<string, mixed>
     */
    private function entry(Entry $entry, int $now): array
    {
        return [
            'cidr' => $entry->cidr,
            'note' => $entry->note,
            'source' => match ($entry->source) {
                Entry::SUGGESTED => __('Suggested'),
                Entry::CONFIG => __('From config'),
                default => __('Manual'),
            },
            'by' => $entry->addedBy,
            'expires' => $entry->expiresAt ? Carbon::createFromTimestamp($entry->expiresAt)->diffForHumans(['parts' => 1]) : __('never'),
            'fromConfig' => $entry->source === Entry::CONFIG,
        ];
    }

    /**
     * "🇩🇪 DE", or "Tor" for Cloudflare's T1. Null when unknown.
     *
     * @return array{code: string, label: string}|null
     */
    public static function country(mixed $code): ?array
    {
        $code = Recorder::country(is_string($code) ? $code : null);

        if ($code === null) {
            return null;
        }

        if ($code === 'T1') {
            return ['code' => 'T1', 'label' => 'Tor'];
        }

        $flag = ctype_alpha($code) ? mb_chr(0x1F1E6 + ord($code[0]) - 65).mb_chr(0x1F1E6 + ord($code[1]) - 65) : '';

        return ['code' => $code, 'label' => trim("{$flag} {$code}")];
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
