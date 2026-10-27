{{-- Statamic 6. Server names come from the hosts, so they sit in v-pre too. --}}
@php
    $code = 'text-xs bg-gray-50 dark:bg-gray-900 rounded-lg p-3 overflow-x-auto';
@endphp
<div class="max-w-page mx-auto space-y-6" v-cloak>
    <div>
        <a href="{{ $blockingUrl }}" class="text-sm underline underline-offset-2">← {{ __('Blocking') }}</a>
        <ui-header title="{{ __('Set up nginx') }}" icon="{{ $icon }}"></ui-header>
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('nginx turns blocked IPs away before PHP runs. Porro keeps a deny file up to date; nginx reads it. Three one-time steps: the first once for the site, the other two on every server it runs on.') }}</p>
    </div>

    @unless ($enforcement['production'])
        <ui-card><p class="text-sm">{{ __('This copy isn’t production, so it doesn’t write the file. Do these steps for the production site and its servers.') }}</p></ui-card>
    @endunless

    @php
        $servers = $progress['servers'];
        $onServers = fn (int $n) => $servers === 0 ? __('No server has checked in yet') : trans_choice('Done on :n of :count server|Done on :n of :count servers', $servers, ['n' => $n, 'count' => $servers]);
    @endphp

    <ui-panel heading="{{ __('1 · Turn on nginx mode') }}" subheading="{{ __('Once, for the site') }} · {{ $enforcement['nginx'] ? '✓ '.__('Done') : __('To do') }}">
        <ui-card>
            <p class="text-sm mb-2">{{ __('Add this to the site’s environment: .env, or on Forge, the site’s Environment tab. Porro then writes the deny file every minute.') }}</p>
            <div class="flex items-start gap-2"><pre v-pre class="{{ $code }} flex-1">PORRO_BLOCK_NGINX=true</pre><ui-button size="sm" data-porro-copy="PORRO_BLOCK_NGINX=true" text="{{ __('Copy') }}"></ui-button></div>
        </ui-card>
    </ui-panel>

    <ui-panel heading="{{ __('2 · Add one line to the site’s nginx config') }}" subheading="{{ __('On every server') }} · {{ ($servers && $progress['included'] === $servers ? '✓ ' : '').$onServers($progress['included']) }}">
        <ui-card>
            <p class="text-sm mb-2">{{ __('Inside server { }, above the first location. On Forge: open the site, then Edit Nginx Configuration from the Files menu, and save.') }}</p>
            <div class="flex items-start gap-2"><pre v-pre class="{{ $code }} flex-1">{{ $enforcement['include'] }}</pre><ui-button size="sm" data-porro-copy="{{ $enforcement['include'] }}" text="{{ __('Copy') }}"></ui-button></div>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-3 mb-1">{{ __('Where it goes, in a trimmed copy of this site’s config:') }}</p>
            <pre v-pre class="{{ $code }}">@foreach ($example as $row)<span style="display: block; min-height: 1.5em;{{ $row['add'] ? ' background: rgb(191 232 74 / .28); margin: 0 -.75rem; padding: 0 .75rem; font-weight: 600;' : '' }}">{{ $row['line'] }}@if ($row['add'])  <span style="font-weight: 400; opacity: .6">← {{ __('add this line') }}</span>@endif</span>@endforeach</pre>
            @if ($realIpInFile)
                <p class="text-xs mt-3">{{ __('Behind Cloudflare: the file also carries Cloudflare’s real IP lines, so nginx turns away the visitor and not Cloudflare. Nothing else to add. If this config already sets real_ip_header, set PORRO_NGINX_REAL_IP=false.') }}</p>
            @elseif ($behindCloudflare)
                <p class="text-xs mt-3 mb-2">{{ __('This site is behind Cloudflare but PORRO_NGINX_REAL_IP is off, so the config needs Cloudflare’s real IP lines too. Without them nginx sees Cloudflare’s address and would deny Cloudflare, not the visitor:') }}</p>
                <div class="flex items-start gap-2"><pre v-pre class="{{ $code }} flex-1">{{ $enforcement['realIp'] }}</pre><ui-button size="sm" data-porro-copy="{{ $enforcement['realIp'] }}" text="{{ __('Copy') }}"></ui-button></div>
            @else
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-3">{{ __('Behind Cloudflare? Porro adds Cloudflare’s real IP lines to the file for you once you open this page through it, or once a Cloudflare token is set.') }}</p>
            @endif
        </ui-card>
    </ui-panel>

    <ui-panel heading="{{ __('3 · Reload nginx when the list changes') }}" subheading="{{ __('On every server') }} · {{ ($servers && $progress['loaded'] === $servers ? '✓ ' : '').$onServers($progress['loaded']) }}">
        <ui-card>
            <p class="text-sm mb-2">{{ __('A scheduled job that runs every minute as root. On Forge: Server → Scheduler → New job, user root, frequency every minute.') }}</p>
            <div class="flex items-start gap-2"><pre v-pre class="{{ $code }} flex-1">{{ $enforcement['reload'] }}</pre><ui-button size="sm" data-porro-copy="{{ $enforcement['reload'] }}" text="{{ __('Copy') }}"></ui-button></div>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">{{ __('It reloads only when the file has changed and nginx -t passes, so a bad config never takes the site down.') }}</p>
        </ui-card>
    </ui-panel>

    <ui-panel heading="{{ __('Servers') }}" subheading="{{ __('Each checks itself every minute') }}">
        <ui-card>
            @forelse ($enforcement['servers'] as $server)
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 py-2 text-sm {{ $loop->first ? '' : 'border-t border-gray-200 dark:border-gray-800' }}">
                    <span v-pre class="font-mono text-xs flex-1">{{ $server['name'] }}</span>
                    @if ($server['ok'] && $behindCloudflare && $server['realIp'] === false)
                        <ui-badge size="sm" color="amber" text="{{ __('Real IP lines missing') }}"></ui-badge>
                    @endif
                    <span class="text-xs text-gray-600 dark:text-gray-400">
                        @if ($server['reloaded']){{ __('reloaded :ago', ['ago' => $server['reloaded']]) }} · @endif{{ __('checked :ago', ['ago' => $server['checked']]) }}
                    </span>
                    <ui-badge size="sm" color="{{ $server['ok'] ? 'green' : ($server['state'] === 'not_included' ? 'red' : 'amber') }}" text="{{ $server['label'] }}"></ui-badge>
                </div>
            @empty
                <p class="text-sm">{{ __('No server has checked in yet. Once step 1 is done, each server checks itself every minute and shows up here.') }}</p>
            @endforelse
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-3">
                {{ __('Each server checks by looking, never by running anything: the file exists, a readable nginx config includes it, and the reload job has run since the last change.') }}
                @unless ($sharedCache) {{ __('This site’s cache isn’t shared between servers, so only this server shows here. Use Redis or the database cache to see them all.') }} @endunless
            </p>
        </ui-card>
    </ui-panel>
</div>
