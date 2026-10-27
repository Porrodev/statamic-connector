# Changelog

## Unreleased

- **Porro has its own section in the control panel nav:** **Site Health** and **Blocking**, at `/cp/porro`. The old Utilities address redirects.
- **New permissions:** **View Porro Site Health** and **Block and safelist IP addresses**. **Access Porro Site Health utility** is no longer used, so give roles that had it **View Porro Site Health** instead.
- **Suspicious activity on Site Health:** probes, failed logins, 404 bursts and form floods, with each flagged IP's rough country and buttons to block or safelist it. An exposed `/.env` or `/.git` shows as **Act now**.
- **The Blocking page:** the blocklist, safelist and protected ranges. Blocks that would break the site are refused with the reason. nginx setup steps show when `PORRO_BLOCK_NGINX` is on.
- Requires `porro/laravel-connector` 0.3. Run `php please migrate` for its blocklist tables.

## 0.2.1

- The utility and widget check the cached result, and what came from Porro, field by field before showing it. Anything malformed is left out, and a result that can't be read shows the "couldn't run" state instead of an error page.
- Only web links from the result become links.
- Requires `porro/laravel-connector` 0.2.1 (requests to Porro signed for their method and path; **Check again** asks Porro again).
- Reports describe the site's database (engine, version, whether it's local, managed provider), through `porro/laravel-connector`, so Porro can flag an end-of-life database.

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
