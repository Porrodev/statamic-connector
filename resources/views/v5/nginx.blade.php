{{-- Statamic 5. Server names come from the hosts, so they sit in v-pre too. --}}
<header class="mb-6">
    <a href="{{ $blockingUrl }}" class="text-sm text-blue hover:text-blue-800">&larr; {{ __('Blocking') }}</a>
    <h1 class="flex items-center mt-1"><span class="h-6 w-6 mr-2 text-gray-800">{!! $icon !!}</span>{{ __('Set up nginx') }}</h1>
    <p class="text-sm text-gray-700 mt-2">{{ __('nginx turns blocked IPs away before PHP runs. Porro keeps a deny file up to date; nginx reads it. Three one-time steps: the first once for the site, the other two on every server it runs on.') }}</p>
</header>

@unless ($enforcement['production'])
    <div class="card mb-6 text-sm">{{ __('This copy isn’t production, so it doesn’t write the file. Do these steps for the production site and its servers.') }}</div>
@endunless

@php
    $servers = $progress['servers'];
    $onServers = fn (int $n) => $servers === 0 ? __('No server has checked in yet') : trans_choice('Done on :n of :count server|Done on :n of :count servers', $servers, ['n' => $n, 'count' => $servers]);
    $tone = fn (bool $ok) => $ok ? 'text-green-600' : 'text-gray-700';
@endphp

<h2 class="mb-2 font-bold flex justify-between">{{ __('1 · Turn on nginx mode') }} <span class="font-normal text-sm {{ $tone($enforcement['nginx']) }}">{{ $enforcement['nginx'] ? __('Done') : __('To do') }}</span></h2>
<div class="card mb-6 text-sm">
    <p class="text-gray-700 mb-2">{{ __('Add this to the site’s environment: .env, or on Forge, the site’s Environment tab. Porro then writes the deny file every minute.') }}</p>
    <div class="flex items-start"><pre v-pre class="text-xs bg-gray-200 rounded p-2 overflow-x-auto flex-1">{{ 'PORRO_BLOCK_NGINX=true' }}</pre><button type="button" class="btn btn-sm ml-2" data-porro-copy="{{ 'PORRO_BLOCK_NGINX=true' }}">{{ __('Copy') }}</button></div>
</div>

<h2 class="mb-2 font-bold flex justify-between">{{ __('2 · Add one line to the site’s nginx config') }} <span class="font-normal text-sm {{ $tone($servers && $progress['included'] === $servers) }}">{{ $onServers($progress['included']) }}</span></h2>
<div class="card mb-6 text-sm">
    <p class="text-gray-700 mb-2">{{ __('Inside server { }, above the first location. On Forge: open the site, then Edit Nginx Configuration from the Files menu, and save.') }}</p>
    <div class="flex items-start"><pre v-pre class="text-xs bg-gray-200 rounded p-2 overflow-x-auto flex-1">{{ $enforcement['include'] }}</pre><button type="button" class="btn btn-sm ml-2" data-porro-copy="{{ $enforcement['include'] }}">{{ __('Copy') }}</button></div>
    <p class="text-xs text-gray-700 mt-3 mb-1">{{ __('Where it goes, in a trimmed copy of this site’s config:') }}</p>
    <pre v-pre class="text-xs bg-gray-200 rounded p-2 overflow-x-auto">@foreach ($example as $row)<span style="display: block; min-height: 1.5em;{{ $row['add'] ? ' background: rgb(191 232 74 / .35); margin: 0 -.5rem; padding: 0 .5rem; font-weight: 600;' : '' }}">{{ $row['line'] }}@if ($row['add'])  <span style="font-weight: 400; opacity: .6">← {{ __('add this line') }}</span>@endif</span>@endforeach</pre>
    @if ($realIpInFile)
        <p class="text-xs mt-3">{{ __('Behind Cloudflare: the file also carries Cloudflare’s real IP lines, so nginx turns away the visitor and not Cloudflare. Nothing else to add. If this config already sets real_ip_header, set PORRO_NGINX_REAL_IP=false.') }}</p>
    @elseif ($behindCloudflare)
        <p class="text-xs mt-3 mb-2">{{ __('This site is behind Cloudflare but PORRO_NGINX_REAL_IP is off, so the config needs Cloudflare’s real IP lines too. Without them nginx sees Cloudflare’s address and would deny Cloudflare, not the visitor:') }}</p>
        <div class="flex items-start"><pre v-pre class="text-xs bg-gray-200 rounded p-2 overflow-x-auto flex-1">{{ $enforcement['realIp'] }}</pre><button type="button" class="btn btn-sm ml-2" data-porro-copy="{{ $enforcement['realIp'] }}">{{ __('Copy') }}</button></div>
    @else
        <p class="text-xs text-gray-700 mt-3">{{ __('Behind Cloudflare? Porro adds Cloudflare’s real IP lines to the file for you once you open this page through it, or once a Cloudflare token is set.') }}</p>
    @endif
</div>

<h2 class="mb-2 font-bold flex justify-between">{{ __('3 · Reload nginx when the list changes') }} <span class="font-normal text-sm {{ $tone($servers && $progress['loaded'] === $servers) }}">{{ $onServers($progress['loaded']) }}</span></h2>
<div class="card mb-6 text-sm">
    <p class="text-gray-700 mb-2">{{ __('A scheduled job that runs every minute as root. On Forge: Server → Scheduler → New job, user root, frequency every minute.') }}</p>
    <div class="flex items-start"><pre v-pre class="text-xs bg-gray-200 rounded p-2 overflow-x-auto flex-1">{{ $enforcement['reload'] }}</pre><button type="button" class="btn btn-sm ml-2" data-porro-copy="{{ $enforcement['reload'] }}">{{ __('Copy') }}</button></div>
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
        <p class="p-4">{{ __('No server has checked in yet. Once step 1 is done, each server checks itself every minute and shows up here.') }}</p>
    @endforelse
    <p class="px-4 pb-4 pt-2 text-xs text-gray-700 border-t">
        {{ __('Each server checks by looking, never by running anything: the file exists, a readable nginx config includes it, and the reload job has run since the last change.') }}
        @unless ($sharedCache) {{ __('This site’s cache isn’t shared between servers, so only this server shows here. Use Redis or the database cache to see them all.') }} @endunless
    </p>
</div>
