# Changelog

## Unreleased (0.2.1)

- Reports describe the site's database (engine, version, whether it's local, managed provider), through `porro/laravel-connector`, so Porro can flag an end-of-life database.
- Requires `porro/laravel-connector` 0.2.1.

## 0.2.0 - 2026-10-23

- **Porro Site Health**: a new utility (Utilities → Porro Site Health) and dashboard widget (`porro_site_health`). Without a Porro account it checks security advisories in `composer.lock`, PHP, Laravel and Statamic support dates, Statamic, addon and Laravel updates, the scheduler, debug mode, migrations, failed jobs, backups and health checks. Once connected, it adds the site's open Porro findings with links into Porro.
- New permission: **Access Porro Site Health utility**. Super admins have it.
- The scheduler writes a heartbeat even before the site is connected, so the utility can tell whether cron runs.
- Requires `porro/laravel-connector` 0.2.

## 0.1.2

- `php please porro:cron` adds the scheduler to the crontab on its own.

## 0.1.1

- `php please porro:connect` offers to set up cron too.

## 0.1.0

- First release: Statamic version, edition and addons in each Porro report, and `php please porro:connect`.
