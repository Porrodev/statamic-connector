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
        <p class="font-bold">nginx ·
            @if (! $enforcement['nginx'])
                <span class="text-gray-600">{{ __('Not set up') }}</span>
            @elseif (! $enforcement['production'])
                <span class="text-gray-600">{{ __('Production only') }}</span>
            @elseif ($enforcement['nginxWritten'])
                <span class="text-green-600">{{ __('File written :ago', ['ago' => $enforcement['nginxWritten']]) }}</span>
            @else
                <span class="text-orange">{{ __('Waiting for the first sync') }}</span>
            @endif
        </p>
        @if ($enforcement['nginx'])
            <p class="text-gray-700 mb-2">{{ __('On each server: include the file in the site’s nginx config (Forge: Site → Edit Nginx Configuration, inside server { }), real IP lines first when behind Cloudflare.') }}</p>
            <pre v-pre class="text-xs bg-gray-200 rounded p-2 overflow-x-auto">{{ $enforcement['realIp'] }}
{{ $enforcement['include'] }}</pre>
            <p class="text-gray-700 my-2">{{ __('Then reload nginx when the list changes: a scheduled job as root, every minute (Forge: Server → Scheduler).') }}</p>
            <pre v-pre class="text-xs bg-gray-200 rounded p-2 overflow-x-auto">{{ $enforcement['reload'] }}</pre>
        @else
            <p class="text-gray-700">{{ __('For sites not behind Cloudflare: set PORRO_BLOCK_NGINX=true and this page shows the two one-time steps for each server.') }}</p>
        @endif
    </div>
</div>

<h2 class="mb-2 font-bold">{{ __('Blocklist') }} <span class="font-normal text-gray-600 text-sm">· {{ trans_choice(':count entry|:count entries', count($blocks), ['count' => count($blocks)]) }}</span></h2>
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
</div>

<h2 class="mb-2 font-bold">{{ __('Always protected') }} <span class="font-normal text-gray-600 text-sm">· {{ __('Can’t be blocked') }}</span></h2>
<div class="card p-0 mb-6 text-sm">
    <p class="p-4 text-gray-700">{{ __('Infrastructure that has to reach this site. Blocks that overlap these are refused, and removed if a list changes.') }}</p>
    @foreach ($protected as $row)
        <div class="px-4 py-2 border-t flex justify-between"><span>{{ $row['label'] }}</span><span class="text-gray-700">{{ trans_choice(':count range|:count ranges', $row['count'], ['count' => $row['count']]) }}</span></div>
    @endforeach
</div>
