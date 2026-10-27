{{-- Statamic 6. Server names come from the hosts, so they sit in v-pre too. --}}
@php($code = 'text-xs bg-gray-50 dark:bg-gray-900 rounded-lg p-3 overflow-x-auto')
<div class="max-w-page mx-auto space-y-6">
    <div>
        <a href="{{ $blockingUrl }}" class="text-sm underline underline-offset-2">← {{ __('Blocking') }}</a>
        <ui-header title="{{ __('Set up nginx') }}" icon="{{ $icon }}"></ui-header>
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('nginx turns blocked IPs away before PHP runs. Porro writes a deny file on each server; nginx includes it and reloads when it changes. Two one-time steps, on every server this site runs on.') }}</p>
    </div>

    @unless ($enforcement['nginx'])
        <ui-card>
            <p class="text-sm mb-2">{{ __('nginx mode is off. Add this to the site’s environment first, so Porro starts writing the file every minute:') }}</p>
            <pre v-pre class="{{ $code }}">PORRO_BLOCK_NGINX=true</pre>
        </ui-card>
    @endunless

    @unless ($enforcement['production'])
        <ui-card><p class="text-sm">{{ __('This copy isn’t production, so it doesn’t write the file. Set these steps up on the production servers.') }}</p></ui-card>
    @endunless

    <ui-panel heading="{{ __('Step 1 · include the file') }}" subheading="{{ __('In the site’s nginx config, inside server { }. On Forge: Site → Edit Nginx Configuration.') }}">
        <ui-card>
            @if ($behindCloudflare)
                <p class="text-sm mb-2">{{ __('This site is behind Cloudflare, so the real IP lines come first: without them nginx sees Cloudflare’s address and would deny Cloudflare, not the visitor.') }}</p>
                <pre v-pre class="{{ $code }}">{{ $enforcement['realIp'] }}{{ $enforcement['include'] }}</pre>
            @else
                <pre v-pre class="{{ $code }}">{{ $enforcement['include'] }}</pre>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">{{ __('Behind Cloudflare or another proxy? Its real IP lines have to come before the include, or nginx would deny the proxy instead of the visitor.') }}</p>
            @endif
        </ui-card>
    </ui-panel>

    <ui-panel heading="{{ __('Step 2 · reload nginx when the list changes') }}" subheading="{{ __('A scheduled job as root, every minute. On Forge: Server → Scheduler → New job, user root.') }}">
        <ui-card>
            <pre v-pre class="{{ $code }}">{{ $enforcement['reload'] }}</pre>
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
                <p class="text-sm">{{ __('No server has reported yet. Once PORRO_BLOCK_NGINX is on, each server checks itself every minute and shows up here.') }}</p>
            @endforelse
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-3">
                {{ __('Each server checks by looking, never by running anything: the file exists, a readable nginx config includes it, and the reload job has run since the last change.') }}
                @unless ($sharedCache) {{ __('This site’s cache isn’t shared between servers, so only this server shows here. Use Redis or the database cache to see them all.') }} @endunless
            </p>
        </ui-card>
    </ui-panel>
</div>
