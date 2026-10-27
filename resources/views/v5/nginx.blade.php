{{-- Statamic 5. Server names come from the hosts, so they sit in v-pre too. --}}
<header class="mb-6">
    <a href="{{ $blockingUrl }}" class="text-sm text-blue hover:text-blue-800">&larr; {{ __('Blocking') }}</a>
    <h1 class="flex items-center mt-1"><span class="h-6 w-6 mr-2 text-gray-800">{!! $icon !!}</span>{{ __('Set up nginx') }}</h1>
    <p class="text-sm text-gray-700 mt-2">{{ __('nginx turns blocked IPs away before PHP runs. Porro writes a deny file on each server; nginx includes it and reloads when it changes. Two one-time steps, on every server this site runs on.') }}</p>
</header>

@unless ($enforcement['nginx'])
    <div class="card mb-6 text-sm">
        <p class="mb-2">{{ __('nginx mode is off. Add this to the site’s environment first, so Porro starts writing the file every minute:') }}</p>
        <pre v-pre class="text-xs bg-gray-200 rounded p-2">PORRO_BLOCK_NGINX=true</pre>
    </div>
@endunless

@unless ($enforcement['production'])
    <div class="card mb-6 text-sm">{{ __('This copy isn’t production, so it doesn’t write the file. Set these steps up on the production servers.') }}</div>
@endunless

<h2 class="mb-2 font-bold">{{ __('Step 1 · include the file') }}</h2>
<div class="card mb-6 text-sm">
    <p class="text-gray-700 mb-2">{{ __('In the site’s nginx config, inside server { }. On Forge: Site → Edit Nginx Configuration.') }}</p>
    @if ($behindCloudflare)
        <p class="mb-2">{{ __('This site is behind Cloudflare, so the real IP lines come first: without them nginx sees Cloudflare’s address and would deny Cloudflare, not the visitor.') }}</p>
        <pre v-pre class="text-xs bg-gray-200 rounded p-2 overflow-x-auto">{{ $enforcement['realIp'] }}{{ $enforcement['include'] }}</pre>
    @else
        <pre v-pre class="text-xs bg-gray-200 rounded p-2 overflow-x-auto">{{ $enforcement['include'] }}</pre>
        <p class="text-xs text-gray-700 mt-2">{{ __('Behind Cloudflare or another proxy? Its real IP lines have to come before the include, or nginx would deny the proxy instead of the visitor.') }}</p>
    @endif
</div>

<h2 class="mb-2 font-bold">{{ __('Step 2 · reload nginx when the list changes') }}</h2>
<div class="card mb-6 text-sm">
    <p class="text-gray-700 mb-2">{{ __('A scheduled job as root, every minute. On Forge: Server → Scheduler → New job, user root.') }}</p>
    <pre v-pre class="text-xs bg-gray-200 rounded p-2 overflow-x-auto">{{ $enforcement['reload'] }}</pre>
    <p class="text-xs text-gray-700 mt-2">{{ __('It reloads only when the file has changed and nginx -t passes, so a bad config never takes the site down.') }}</p>
</div>

<h2 class="mb-2 font-bold">{{ __('Servers') }} <span class="font-normal text-gray-600 text-sm">· {{ __('Each checks itself every minute') }}</span></h2>
<div class="card p-0 mb-6 text-sm">
    @forelse ($enforcement['servers'] as $server)
        <div class="flex flex-wrap items-center px-4 py-2 {{ $loop->first ? '' : 'border-t' }}">
            <span v-pre class="font-mono text-xs flex-1">{{ $server['name'] }}</span>
            @if ($server['ok'] && $behindCloudflare && $server['realIp'] === false)
                <span class="text-orange text-xs mr-4">{{ __('Real IP lines missing') }}</span>
            @endif
            <span class="text-gray-700 text-xs mr-4">@if ($server['reloaded']){{ __('reloaded :ago', ['ago' => $server['reloaded']]) }} · @endif{{ __('checked :ago', ['ago' => $server['checked']]) }}</span>
            <span class="{{ $server['ok'] ? 'text-green-600' : ($server['state'] === 'not_included' ? 'text-red-500' : 'text-orange') }}">{{ $server['label'] }}</span>
        </div>
    @empty
        <p class="p-4">{{ __('No server has reported yet. Once PORRO_BLOCK_NGINX is on, each server checks itself every minute and shows up here.') }}</p>
    @endforelse
    <p class="px-4 pb-4 pt-2 text-xs text-gray-700 border-t">
        {{ __('Each server checks by looking, never by running anything: the file exists, a readable nginx config includes it, and the reload job has run since the last change.') }}
        @unless ($sharedCache) {{ __('This site’s cache isn’t shared between servers, so only this server shows here. Use Redis or the database cache to see them all.') }} @endunless
    </p>
</div>
