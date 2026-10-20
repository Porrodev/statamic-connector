# porro/statamic-connector

[Porro](https://porro.dev) connector for Statamic 5–6 sites. It includes [`porro/laravel-connector`](https://github.com/Porrodev/laravel-connector) and adds Statamic's own details to each report. Read-only: it never changes the site and never accepts commands.

## Install

```bash
composer require porro/statamic-connector
php please porro:connect <site key>
```

Copy the site key from the site's page in Porro. `porro:connect` offers to add the scheduler's cron line for you; if you skip it, run `php please porro:cron` on the server later. Reports then go every 15 minutes on the scheduler; see the [Laravel connector](https://github.com/Porrodev/laravel-connector) for config and commands.

## What it adds

- Statamic version, and whether the site runs Pro
- Installed addons and their versions
- Static caching strategy, whether Git automation is on, and whether the Stache watcher is on

Content, entries, assets and users never leave the site. Run `php artisan porro:status --json` to see the exact report.

## Developing

```bash
composer install
composer test
```

## Security

Please email security@porro.dev rather than opening an issue. We reply within one working day.

## License

MIT
