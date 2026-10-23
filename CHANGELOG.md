# Changelog

## Unreleased (0.2.0)

- **Site health**: a new utility (Utilities → Site health) and dashboard widget (`porro_site_health`). Without a Porro account it checks security advisories in `composer.lock`, PHP, Laravel and Statamic support dates, Statamic, addon and Laravel updates, the scheduler, debug mode, migrations, failed jobs, backups and health checks. Once connected, it adds the site's open Porro findings with links into Porro.
- New permission: **Access Site health utility**. Super admins have it.
- The scheduler writes a heartbeat even before the site is connected, so the utility can tell whether cron runs.
- Requires `porro/laravel-connector` 0.2.

## 0.1.2

- `php please porro:cron` adds the scheduler to the crontab on its own.

## 0.1.1

- `php please porro:connect` offers to set up cron too.

## 0.1.0

- First release: Statamic version, edition and addons in each Porro report, and `php please porro:connect`.
