# Changelog


## 0.3.4 - 2026-10-28

- Requires `porro/laravel-connector` and `porro/php-core` 0.3.5. A suspicious request no longer touches the database: it appends a line to `storage/porro/activity.buf` on its server, and the lines are recorded in batches, every minute by the scheduler and before the Activity, IP and Site health pages read. Counts stay exact during a flood. `PORRO_ACTIVITY_BUFFER=false` records each request straight away, as happens anyway where storage can't be written or on Lambda (Vapor).
## 0.3.3 - 2026-10-28

- **Porro → Blocking → Set up Cloudflare**: a setup page with three steps that tick themselves off. It lists the token's two permission policies as Cloudflare's dashboard names them (Zone WAF Rules: Edit and Zone: Read on the site's domain, Account Rule Lists: Edit on the account), the `.env` line with a copy button, and a **Connect to Cloudflare** button for users who can manage blocking. It says plainly that `porro_blocklist` is shared by every site on the Cloudflare account.
- The IP details page sums up what an IP did over 48 hours, flagged or not:
  - requests per hour, total requests, and how many a block turned away;
  - what its probes were after;
  - its top paths with their last status, and its user agents;
  - its hosts and methods. On Statamic 5 and 6; every value a visitor sends stays out of Vue's reach (`v-pre`).
- It also shows requests through Cloudflare versus straight to the server, saying when blocking at Cloudflare won't stop them.
- Through laravel-connector 0.3.4, activity survives deploys in Porro's own `porro_cache` table (run `php artisan migrate`), and floods are bounded.
- Requires `porro/laravel-connector` 0.3.4 and `porro/php-core` 0.3.4.

## 0.3.2 - 2026-10-28

- The package's description covers suspicious activity and IP blocking as well as site health, on Packagist and in the Statamic Marketplace.

## 0.3.1 - 2026-10-28

- The Blocking page's nginx row said setup takes "two one-time steps on each server". It takes three: one for the site and two on each server, as the setup page says.
- The README shows Activity, IP details and Blocking, with screenshots.

## 0.3.0 - 2026-10-27

- **The activity table is ranked by what's known about each IP**, with badges next to it: Spamhaus, Tor, Cloudflare risk, Fake crawler, Hosting, or "Scanner, low risk" for research scanners, which go last.
- **IP details**, opened by clicking an IP in Site Health's activity table:
  - **Who it is:** country, network, a verified hostname, and badges for Tor exits, Spamhaus DROP, hosting providers and research scanners.
  - **Crawlers:** a plain note when an IP is pretending to be Googlebot (or Bing, Apple, Yandex), and when it's the real thing.
  - **Activity:** the last 48 hours here.
  - **Actions:** block it or its /24 (hosting networks only), or safelist it.
  - **Reputation:** an AbuseIPDB check that runs only when clicked, and only for people who can manage the blocklist.
- **nginx setup page**, linked from Blocking: three numbered steps, each saying how many servers have done it, with copy buttons and a trimmed copy of the site's config showing where the include line goes. Behind Cloudflare the deny file carries the real-IP lines itself, so the config needs one line.
  - **Every server:** each one's progress, checked by the server itself every minute: no file yet, include missing, waiting for reload, or loaded.
  - **Blocking page:** the nginx row now says how many servers are loaded, rather than repeating the snippets.
- **The Blocking page shows Cloudflare WAF mode:** whether it's set up and connected, the zone, the last sync, IPs already blocked by another site on the account, and any error. Until it's connected, it shows the setup steps. Requires `porro/laravel-connector`'s Cloudflare WAF mode.
- **Porro has its own section in the control panel nav:** **Site Health** and **Blocking**, at `/cp/porro`. The old Utilities address redirects.
- **New permissions:** **View Porro Site Health** and **Block and safelist IP addresses**. **Access Porro Site Health utility** is no longer used, so give roles that had it **View Porro Site Health** instead.
- **Suspicious activity on Site Health:** probes, failed logins, 404 bursts and form floods, with each flagged IP's rough country and buttons to block or safelist it. An exposed `/.env` or `/.git` shows as **Act now**.
- **The Blocking page:** the blocklist, safelist and protected ranges. Blocks that would break the site are refused with the reason. nginx setup steps show when `PORRO_BLOCK_NGINX` is on.
- **Every block and safelist asks first**, in the panel's own confirmation dialog, naming what will happen, to whom and for how long (a range says how many addresses it covers). **Safelist** from the activity table or IP details now adds the IP in one click once confirmed.
- **Always protected** lists open to show why each can't be blocked, where it comes from and its addresses. Google's and Bing's verified crawlers are protected too; `PORRO_PROTECT_FORGE` and `PORRO_PROTECT_CRAWLERS` switch those lists off. A protected IP's details page says so and offers no block buttons.
- **The blocklist and safelist have their own pages**: search by IP (finding the ranges that cover it), range, reason or who added it; filter by manual, suggested, from config or expiring within 7 days; 50 a page. Blocking shows the newest 10 of each with a link to the rest.
- Statamic 6 pages no longer flash unstyled while the control panel loads.
- Requires `porro/laravel-connector` and `porro/php-core` ^0.3.0, from Packagist. Run `php please migrate` for its blocklist tables.

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
