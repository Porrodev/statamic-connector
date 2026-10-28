{{-- Statamic 5. Notes and labels are typed by people, so they sit in v-pre too. --}}
<header class="mb-6">
    <div class="flex items-center justify-between">
        <h1 class="flex items-center"><span class="h-6 w-6 mr-2 text-gray-800">{!! $icon !!}</span>{{ __('Blocking') }}</h1>
        <a href="{{ $healthUrl }}" class="btn">{{ __('Site Health') }}</a>
    </div>
</header>

@include('porro::v5.flash')

@if ($failed)
    <div class="card mb-6"><p class="text-sm">{{ __('The blocklist tables aren’t there yet. Run php please migrate (or php artisan migrate) on this site.') }}</p></div>
@endif

<h2 class="mb-2 font-bold">{{ __('Where blocks are enforced') }}</h2>
<div class="card p-0 mb-6 text-sm">
    <div class="p-4">
        <p class="font-bold">{{ __('In the app') }} · <span class="{{ $enforcement['app'] ? 'text-green-600' : 'text-gray-600' }}">{{ $enforcement['app'] ? __('Active') : __('Off') }}</span></p>
        <p class="text-gray-700">{{ __('Blocked IPs get a 403 before Statamic handles the request. Turn off with PORRO_BLOCK_APP=false.') }}</p>
    </div>
    <div class="p-4 border-t">
        <p class="font-bold">Cloudflare WAF ·
            @if (! $cloudflare['configured'])
                <span class="text-gray-600">{{ __('Not set up') }}</span>
            @elseif (! $cloudflare['connected'])
                <span class="text-orange">{{ __('Token set, not connected') }}</span>
            @elseif ($cloudflare['error'])
                <span class="text-red-500">{{ __('Sync failed') }}</span>
            @elseif (! $cloudflare['production'])
                <span class="text-gray-600">{{ __('Production only') }}</span>
            @elseif ($cloudflare['synced'])
                <span class="text-green-600">{{ __('Synced :ago', ['ago' => $cloudflare['synced']]) }}</span>
            @else
                <span class="text-orange">{{ __('Waiting for the first sync') }}</span>
            @endif
        </p>
        @if ($cloudflare['connected'])
            <p class="text-gray-700">{{ __('Blocked at the edge, before traffic reaches the server · zone :zone · list porro_blocklist', ['zone' => $cloudflare['zone']]) }}@if ($cloudflare['result'] && $cloudflare['result']['shared']) · {{ trans_choice(':count already blocked by another site|:count already blocked by other sites', $cloudflare['result']['shared'], ['count' => $cloudflare['result']['shared']]) }}@endif</p>
        @elseif ($cloudflare['configured'])
            <p class="text-gray-700 mb-2">{{ __('Run this on the server to find the zone, create the porro_blocklist list and add the rule next to your existing ones:') }}</p>
            <pre v-pre class="text-xs bg-gray-200 rounded p-2 overflow-x-auto">php please porro:cloudflare connect</pre>
        @else
            <p class="text-gray-700 mb-2">{{ __('Blocks at Cloudflare’s edge for sites behind it. Create a token with Account Filter Lists Edit, Zone WAF Edit and Zone Read, then:') }}</p>
            <pre v-pre class="text-xs bg-gray-200 rounded p-2 overflow-x-auto">PORRO_CLOUDFLARE_TOKEN=your-token-here
php please porro:cloudflare connect</pre>
        @endif
        @if ($cloudflare['error'])
            <p v-pre class="text-red-500 mt-1">{{ $cloudflare['error'] }}</p>
        @endif
    </div>
    <div class="p-4 border-t">
        <p class="font-bold">nginx ·
            @php($loaded = collect($enforcement['servers'])->where('ok', true)->count())
            @if (! $enforcement['nginx'])
                <span class="text-gray-600">{{ __('Not set up') }}</span>
            @elseif (! $enforcement['production'])
                <span class="text-gray-600">{{ __('Production only') }}</span>
            @elseif ($enforcement['servers'] && $loaded === count($enforcement['servers']))
                <span class="text-green-600">{{ __('Loaded') }}</span>
            @else
                <span class="text-orange">{{ __('Setting up') }}</span>
            @endif
        </p>
        <p class="text-gray-700">
            @if ($enforcement['servers'])
                {{ trans_choice(':loaded of :count server loaded|:loaded of :count servers loaded', count($enforcement['servers']), ['loaded' => $loaded, 'count' => count($enforcement['servers'])]) }} ·
                <a href="{{ $nginxUrl }}" class="text-blue hover:text-blue-800">{{ __('Setup and servers') }} &rarr;</a>
            @else
                {{ __('For sites not behind Cloudflare, or as well as it. Three one-time steps: one for the site, two on each server.') }}
                <a href="{{ $nginxUrl }}" class="text-blue hover:text-blue-800">{{ __('Set up nginx') }} &rarr;</a>
            @endif
        </p>
    </div>
</div>

<h2 class="mb-2 font-bold">{{ __('Blocklist') }} <span class="font-normal text-gray-600 text-sm">· {{ trans_choice(':count entry|:count entries', $blockCount, ['count' => number_format($blockCount)]) }}</span></h2>
<div class="card p-0 mb-6">
    @if ($canManage)
        <form method="POST" action="{{ $urls['block'] }}" data-form="block" class="flex flex-wrap items-center p-4 border-b">
            @csrf
            <input type="hidden" name="form" value="block">
            <input name="ip" required value="{{ old('form') === 'block' ? old('ip') : '' }}" placeholder="{{ __('IP or range, e.g. 203.0.113.0/24') }}" class="input-text mr-2 mb-1" style="max-width: 18rem" aria-label="{{ __('IP or range') }}">
            <input name="reason" value="{{ old('form') === 'block' ? old('reason') : '' }}" placeholder="{{ __('Reason') }}" class="input-text mr-2 mb-1 flex-1" style="min-width: 10rem" aria-label="{{ __('Reason') }}">
            <div class="select-input-container mr-2 mb-1" style="width: 10rem">
                <select name="days" class="select-input" aria-label="{{ __('Expires') }}">
                    <option value="7">{{ __('For 7 days') }}</option>
                    <option value="30">{{ __('For 30 days') }}</option>
                    <option value="0">{{ __('Never expires') }}</option>
                </select>
            </div>
            <button type="submit" class="btn-primary mb-1">{{ __('Block') }}</button>
        </form>
    @endif
    @include('porro::v5.entries', ['entries' => $blocks, 'remove' => $urls['unblock'], 'empty' => __('Nothing is blocked.')])
            @if ($blockCount > count($blocks))<p class="mt-3 px-4 pb-4"><a href="{{ $blocklistUrl }}" class="text-sm text-blue hover:text-blue-800">{{ __('Show all :count, with search →', ['count' => number_format($blockCount)]) }}</a></p>@elseif ($blockCount > 0)<p class="mt-3 px-4 pb-4"><a href="{{ $blocklistUrl }}" class="text-sm text-blue hover:text-blue-800">{{ __('Search the blocklist →') }}</a></p>@endif
</div>

<h2 id="safelist" class="mb-2 font-bold">{{ __('Safelist') }} <span class="font-normal text-gray-600 text-sm">· {{ __('Never blocked or flagged') }}</span></h2>
<div class="card p-0 mb-6">
    @if ($canManage)
        <form method="POST" action="{{ $urls['safelist'] }}" data-form="safelist" class="flex flex-wrap items-center p-4 border-b">
            @csrf
            <input type="hidden" name="form" value="safelist">
            <input name="ip" required value="{{ old('form') === 'safelist' ? old('ip') : $prefill }}" placeholder="{{ __('IP or range') }}" class="input-text mr-2 mb-1" style="max-width: 18rem" aria-label="{{ __('IP or range') }}">
            <input name="label" required value="{{ old('form') === 'safelist' ? old('label') : '' }}" placeholder="{{ __('Label, e.g. 1994 office') }}" class="input-text mr-2 mb-1 flex-1" style="min-width: 10rem" aria-label="{{ __('Label') }}">
            <label class="text-xs text-gray-700 mr-2 mb-1"><input type="checkbox" name="confirm_wide" value="1"> {{ __('Wide range is OK') }}</label>
            <button type="submit" class="btn-primary mb-1">{{ __('Add') }}</button>
        </form>
    @endif
    @include('porro::v5.entries', ['entries' => $safe, 'remove' => $urls['unsafelist'], 'empty' => __('Nothing is safelisted.')])
            @if ($safeCount > count($safe))<p class="mt-3 px-4 pb-4"><a href="{{ $safelistUrl }}" class="text-sm text-blue hover:text-blue-800">{{ __('Show all :count, with search →', ['count' => number_format($safeCount)]) }}</a></p>@endif
</div>

<h2 class="mb-2 font-bold">{{ __('Always protected') }} <span class="font-normal text-gray-600 text-sm">· {{ __('Can’t be blocked') }}</span></h2>
<div class="card p-0 mb-6 text-sm">
    <p class="p-4 text-gray-700">{{ __('Addresses that have to reach this site. Blocks that overlap these are refused, and removed if a list changes. Open one to see its addresses and why.') }}</p>
    @foreach ($protected as $row)
        <details class="px-4 py-2 border-t">
            <summary class="flex justify-between cursor-pointer">
                <span>{{ $row['label'] }}</span>
                <span class="text-gray-700">{{ $row['on'] ? trans_choice(':count range|:count ranges', $row['count'], ['count' => $row['count']]) : __('Switched off') }}</span>
            </summary>
            <div class="mt-2 text-xs">
                <p class="mb-2">{{ $row['why'] }}</p>
                @if ($row['from'])
                    <p class="text-gray-700 mb-2">{{ __('From') }}: @foreach ($row['from'] as $url)<a href="{{ $url }}" target="_blank" rel="noopener" class="text-blue">{{ parse_url($url, PHP_URL_HOST) }}{{ parse_url($url, PHP_URL_PATH) }}</a>@if (! $loop->last) · @endif @endforeach</p>
                @endif
                @if ($row['env'])
                    <p class="text-gray-700 mb-2">{{ $row['on'] ? __('Not needed here? Switch it off with :setting.', ['setting' => $row['env'].'=false']) : __('Switched off with :setting. Remove it to protect these again.', ['setting' => $row['env'].'=false']) }}</p>
                @endif
                @if ($row['ranges'])
                    <div v-pre class="font-mono bg-gray-200 rounded p-2" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(24ch, 1fr)); gap: 2px 16px; max-height: 240px; overflow-y: auto;">@foreach ($row['ranges'] as $range)<span>{{ $range }}</span>@endforeach</div>
                @endif
            </div>
        </details>
    @endforeach
</div>
