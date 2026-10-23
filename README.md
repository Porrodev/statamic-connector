# Porro Site Health for Statamic

Site health for Statamic 5 and 6, inside the control panel. It checks the site for the things that quietly go wrong between projects (security advisories in your Composer packages, PHP, Laravel and Statamic versions running out of security support, updates waiting, a scheduler that never runs) and tells you what to do about each.

It's free and works on its own. If you use [Porro](https://porro.dev) to look after your sites, connecting it adds what can only be seen from outside: uptime, SSL certificates, domain expiry and the server's operating system.

![Utilities → Site health on a Statamic 6 site that isn't connected to Porro: a migration not run, the scheduler not running and three failed jobs, each with the command that fixes it](docs/images/statamic-site-health.png)

## Install

```bash
composer require porro/statamic-connector
```

Then open **Utilities → Site health**. Nothing else to set up.

### Dashboard widget

Add it to `config/statamic/cp.php`:

```php
'widgets' => [
    ['type' => 'porro_site_health', 'width' => 50],
],
```

It lists the five most important issues (`'limit' => 5` to change that) and links to the utility. It shows the last result rather than running the checks, so the dashboard never waits on them.

<img src="docs/images/statamic-widget.png" alt="The Site health dashboard widget, listing what needs attention" width="420">

### Who can see it

Super admins. To let other roles see it, give them **Access Site health utility** under Utilities in **Users → Roles**. The widget and the "Check again" button follow the same permission.

## What it checks

On the site itself, with no account:

| Check | How |
|---|---|
| Security advisories in `composer.lock` | [Packagist's security advisories API](https://packagist.org/apidoc#list-security-advisories), the one `composer audit` uses. Only package names are sent; versions are matched on your server. High and critical advisories show as **Act now**. |
| PHP, Laravel and Statamic support | Past, or within 90 days of, the end of security support, from [endoflife.date](https://endoflife.date). A copy ships with the addon for when it can't be reached. |
| Updates | Statamic and addon updates from Statamic's own updater (flagged when one is a security release), and newer Laravel releases on the major version you run |
| Scheduler | Whether `schedule:run` is running, from a heartbeat the addon schedules every 15 minutes, and whether the crontab has the line. `php please porro:cron` adds it. |
| Debug mode, migrations, failed jobs | `APP_DEBUG` on in production, migrations not run, failed jobs in the last 24 hours |
| Backups and health checks | Stale backups from `spatie/laravel-backup` and failing `spatie/laravel-health` checks, if you use them |

Results are cached for five minutes, and the public data behind them for between six hours and a day, so the page is quick and the APIs aren't asked often. **Check again** runs everything now.

## Connect Porro (optional)

Porro watches every site you look after from outside: uptime from several regions, SSL and domain expiry, the server's OS and security updates, with alerts by email or Slack. It's a separate service with its own account; see [porro.dev](https://porro.dev) for plans. Nothing in this addon needs it, and nothing is sent to Porro until you connect. Add the site in Porro, then:

```bash
php please porro:connect <site key>
```

The utility then shows the site's open findings in Porro alongside the checks above, each linking to Porro.

![Site health connected to Porro, in dark mode: an end-of-life server, an expiring certificate and domain, and a slow region, above the checks run on the site](docs/images/statamic-site-health-dark.png) Connecting also sends Porro a signed report every 15 minutes on Laravel's scheduler: versions, packages and the health facts above, never content, users or settings values. Run `php artisan porro:status --json` to see exactly what would be sent, and see the [Laravel connector](https://github.com/Porrodev/laravel-connector) for config and commands.

`php please porro:connect` offers to add the scheduler's cron line; `php please porro:cron` adds it on its own.

## Requirements

- Statamic 5 or 6, Laravel 11–13, PHP 8.2+
- Outbound HTTPS to `packagist.org`, `repo.packagist.org` and `endoflife.date` (and `app.porro.dev` once connected). Without it, the checks that need them say so and the rest still run.

## External requests

| To | When | What's sent |
|---|---|---|
| `packagist.org` | Advisory check, at most every 6 hours or when `composer.lock` changes | Package names from `composer.lock` |
| `repo.packagist.org` | Laravel update check, at most twice a day | Nothing beyond the request |
| `endoflife.date` | Support dates, at most once a day | Nothing beyond the request |
| `app.porro.dev` | Only once connected: reports every 15 minutes, findings when the utility is open (cached 5 minutes) | Signed with the site key |

## Support

Questions and bugs: [GitHub issues](https://github.com/Porrodev/statamic-connector/issues). Security issues: please email security@porro.dev rather than opening an issue. We reply within one working day.

## Demo

[Dog-ear Books](https://github.com/Porrodev/statamic-demo) is a demo site with the addon set up and things for it to find, a stand-in Porro for the connected view, and the end-to-end checks and screenshot script behind these images.

## Developing

```bash
composer install
composer test
```

## License

MIT. See [CHANGELOG.md](CHANGELOG.md) for what changed.
