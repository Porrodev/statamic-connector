{{-- Statamic 5. Hostnames, user agents and paths come from visitors and DNS, so they sit in v-pre. --}}
@php($tones = ['danger' => 'text-red-500', 'warning' => 'text-orange', 'neutral' => 'text-gray-700'])
<header class="mb-6">
    <a href="{{ $healthUrl }}" class="text-sm text-blue hover:text-blue-800">&larr; {{ __('Site Health') }}</a>
    <h1 v-pre class="font-mono mt-1">{{ $details['ip'] }}</h1>
    <p class="text-sm text-gray-700">
        {{ $details['country']['label'] ?? __('Country unknown') }}
        @if ($details['activity'] && $details['activity']['first']) · {{ __('first seen here :ago', ['ago' => $details['activity']['first']]) }} @endif
        @if ($details['blocked']) · <span class="text-red-500">{{ __('blocked') }}</span> @endif
        @if ($details['safelisted']) · {{ __('safelisted') }} @endif
        @if ($details['protected'] ?? null) · {{ __('always protected: :list', ['list' => $details['protected']]) }} @endif
    </p>
    @if ($details['badges'])
        <p class="mt-2">
            @foreach ($details['badges'] as $badge)
                <span class="badge-pill-sm {{ $tones[$badge['tone']] ?? '' }}">{{ $badge['label'] }}</span>
            @endforeach
        </p>
    @endif
</header>

@include('porro::v5.flash')

@if ($details['crawler'])
    <div v-pre class="card mb-6 text-sm">
        <strong class="{{ $details['crawler']['real'] ? 'text-green-600' : 'text-red-500' }}">{{ $details['crawler']['title'] }}.</strong> {{ $details['crawler']['body'] }}
    </div>
@endif

@if ($canManage && ! $details['blocked'] && ! $details['safelisted'] && ! ($details['protected'] ?? null))
    <div class="flex flex-wrap items-center mb-6">
        @foreach ([[__('Block permanently'), 0, $details['ip'], 'btn-primary'], [__('Block 7 days'), 7, $details['ip'], 'btn'], ...($details['range'] ? [[__('Block :range', ['range' => $details['range']]), 7, $details['range'], 'btn']] : [])] as [$label, $days, $target, $class])
            <form method="POST" action="{{ $blockUrl }}" class="mr-2 mb-1" data-porro-confirm="{{ \Porro\Laravel\Activity\Confirm::attribute(\Porro\Laravel\Activity\Confirm::block($target, $days)) }}">
                @csrf
                <input type="hidden" name="ip" value="{{ $target }}">
                <input type="hidden" name="reason" value="{{ $details['reason'] }}">
                <input type="hidden" name="days" value="{{ $days }}">
                @if ($days)<input type="hidden" name="suggested" value="1">@endif
                <button type="submit" class="{{ $class }}">{{ $label }}</button>
            </form>
        @endforeach
        <form method="POST" action="{{ $safelistUrl }}" class="mb-1" data-porro-confirm="{{ \Porro\Laravel\Activity\Confirm::attribute(\Porro\Laravel\Activity\Confirm::safelist($details['ip'])) }}">
                    @csrf
                    <input type="hidden" name="ip" value="{{ $details['ip'] }}">
                    <input type="hidden" name="label" value="{{ __('From the IP details page') }}">
                    <button type="submit" class="text-blue hover:text-blue-800 text-sm ml-1">{{ __('Safelist') }}</button>
                </form>
    </div>
@endif

<h2 class="mb-2 font-bold">{{ __('Network') }}</h2>
<div class="card p-0 mb-6 text-sm">
    @forelse ($details['network'] as $label => $value)
        <div class="flex px-4 py-2 {{ $loop->first ? '' : 'border-t' }}">
            <span class="text-gray-700" style="width: 8rem; flex: none">{{ __($label) }}</span>
            <span v-pre class="{{ in_array($label, ['Hostname', 'User agent'], true) ? 'font-mono text-xs' : '' }}" style="overflow-wrap: anywhere">{{ $value }}</span>
        </div>
    @empty
        <p class="p-4">{{ __('Nothing more is known about this network. Connect Cloudflare for the network and its type.') }}</p>
    @endforelse
</div>

{{-- Paths, hosts and user agents come from the visitor: v-pre, so Vue never reads them as templates. --}}
<h2 class="mb-2 font-bold">{{ __('What it did here') }} <span class="font-normal text-gray-600 text-sm">· {{ __('requests per hour, last 48 hours') }}</span></h2>
<div class="card mb-6 text-sm">
    @if ($details['activity'])
        @php($did = $details['activity'])
        <div class="flex items-end" style="gap: 2px; height: 44px" aria-hidden="true">
            @foreach ($did['hours'] as $n)
                <span style="flex: 1; border-radius: 1px; height: {{ $n ? max(6, (int) round($n / $did['peak'] * 44)) : 2 }}px; background: {{ $n > 10 ? '#dc2626' : ($n > 0 ? '#ea8033' : '#e5e7eb') }}"></span>
            @endforeach
        </div>
        <div class="flex justify-between text-xs text-gray-700 mt-1 mb-3"><span>{{ __('48 h ago') }}</span><span>{{ __('now') }}</span></div>
        @foreach ($did['summary'] as $label => $value)
            <div class="flex py-2 border-t">
                <span class="text-gray-700" style="width: 8rem; flex: none">{{ __($label) }}</span>
                <span v-pre style="overflow-wrap: anywhere">{{ $value }}</span>
            </div>
        @endforeach
        @if ($did['direct'])
            <p class="text-xs text-orange mt-3">{{ __('Some of its requests came straight to the server’s own address, not through Cloudflare. Blocking at Cloudflare won’t stop those: block in the app or in nginx, or let only Cloudflare reach the server at its firewall.') }}</p>
        @endif
        @unless ($did['flagged'])
            <p class="text-xs text-gray-700 mt-3">{{ __('Recorded, but under the thresholds that flag an IP. Only its suspicious requests are counted until it’s flagged.') }}</p>
        @endunless
    @else
        <p>{{ __('Nothing recorded from this IP in the last 48 hours.') }}</p>
    @endif
</div>

@if ($details['activity'] && $details['activity']['paths'])
    <h2 class="mb-2 font-bold">{{ __('Paths it asked for') }} <span class="font-normal text-gray-600 text-sm">· {{ __('requests, last answer') }}</span></h2>
    <div class="card mb-6 text-sm">
        @foreach ($details['activity']['paths'] as $p)
            <div class="flex py-1 font-mono text-xs {{ $loop->first ? '' : 'border-t' }}">
                <span v-pre class="flex-1" style="overflow-wrap: anywhere">{{ $p['path'] }}</span>
                <span class="text-gray-700 mx-3">×{{ number_format($p['count']) }}</span>
                <span class="{{ $p['status'] >= 200 && $p['status'] < 300 ? 'text-red-500' : 'text-gray-700' }}">{{ $p['status'] ?: '—' }}</span>
            </div>
        @endforeach
        @if ($details['activity']['otherPaths'])
            <p class="text-xs text-gray-700 mt-2">{{ trans_choice('…and :count more request to other paths.|…and :count more requests to other paths.', $details['activity']['otherPaths'], ['count' => number_format($details['activity']['otherPaths'])]) }}</p>
        @endif
    </div>
@endif

@if ($details['activity'] && $details['activity']['agents'])
    <h2 class="mb-2 font-bold">{{ __('User agents') }}</h2>
    <div class="card mb-6 text-sm">
        @foreach ($details['activity']['agents'] as $a)
            <div class="flex py-1 font-mono text-xs {{ $loop->first ? '' : 'border-t' }}">
                <span v-pre class="flex-1" style="overflow-wrap: anywhere">{{ $a['agent'] }}</span>
                <span class="text-gray-700 ml-3">×{{ number_format($a['count']) }}</span>
            </div>
        @endforeach
    </div>
@endif

@if ($details['activity'] && $details['activity']['requests'])
    <h2 class="mb-2 font-bold">{{ __('Latest requests') }}</h2>
    <div class="card mb-6 text-sm">
        @foreach ($details['activity']['requests'] as $r)
            <div class="flex py-1 font-mono text-xs {{ $loop->first ? '' : 'border-t' }}">
                <span class="text-gray-700 mr-3" style="width: 3rem">{{ $r['method'] }}</span>
                <span v-pre class="flex-1" style="overflow-wrap: anywhere">{{ $r['path'] }}</span>
                <span class="{{ $r['status'] >= 200 && $r['status'] < 300 ? 'text-red-500' : 'text-gray-700' }} mx-3">{{ $r['status'] ?: '—' }}</span>
                <span class="text-gray-700">{{ $r['time'] }}</span>
            </div>
        @endforeach
    </div>
@endif

<h2 class="mb-2 font-bold">{{ __('Reputation') }} <span class="font-normal text-gray-600 text-sm">· AbuseIPDB</span></h2>
<div class="card mb-6 text-sm">
    @if ($details['reputation'])
        <p><strong class="{{ $details['reputation']['score'] >= 50 ? 'text-red-500' : '' }}">{{ $details['reputation']['score'] }}%</strong> {{ __('abuse confidence') }} · {{ trans_choice(':count report|:count reports', $details['reputation']['reports'], ['count' => number_format($details['reputation']['reports'])]) }}@if ($details['reputation']['isp']) · <span v-pre>{{ $details['reputation']['isp'] }}</span>@endif</p>
    @elseif ($details['canCheckReputation'] && $canManage)
        <form method="POST" action="{{ $reputationUrl }}" class="flex items-center">
            @csrf
            <button type="submit" class="btn btn-sm mr-3">{{ __('Check on AbuseIPDB') }}</button>
            <span class="text-xs text-gray-700">{{ __('Sends this IP to AbuseIPDB.') }}</span>
        </form>
    @else
        <p class="text-gray-700">{{ __('Not checked. Set PORRO_ABUSEIPDB_KEY to check an IP’s reputation on request (it sends the IP to AbuseIPDB).') }}</p>
    @endif
</div>

<p class="text-xs text-gray-700">
    {{ __('Sources') }}: {{ implode(' · ', $details['sources']) }} · {{ $details['fetched'] }}
    @if ($details['attribution']) · <a href="{{ $details['attribution']['url'] }}" target="_blank" rel="noopener" class="text-blue">{{ $details['attribution']['text'] }}</a>@endif
</p>

@include('porro::v5.confirm')
