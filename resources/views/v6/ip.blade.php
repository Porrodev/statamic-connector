{{-- Statamic 6. Hostnames, user agents and paths come from visitors and DNS, so they sit in v-pre. --}}
@php($tones = ['danger' => 'red', 'warning' => 'amber', 'neutral' => 'default'])
<div class="max-w-page mx-auto space-y-6" v-cloak>
    <div>
        <a href="{{ $healthUrl }}" class="text-sm underline underline-offset-2">← {{ __('Site Health') }}</a>
        <h1 v-pre class="font-mono text-2xl font-medium mt-1">{{ $details['ip'] }}</h1>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ $details['country']['label'] ?? __('Country unknown') }}
            @if ($details['activity'] && $details['activity']['first']) · {{ __('first seen here :ago', ['ago' => $details['activity']['first']]) }} @endif
            @if ($details['blocked']) · <span style="color: rgb(220 38 38)">{{ __('blocked') }}</span> @endif
            @if ($details['safelisted']) · {{ __('safelisted') }} @endif
        </p>
        @if ($details['badges'])
            <div class="flex flex-wrap gap-1 mt-2">
                @foreach ($details['badges'] as $badge)
                    <ui-badge size="sm" color="{{ $tones[$badge['tone']] ?? 'default' }}" text="{{ $badge['label'] }}"></ui-badge>
                @endforeach
            </div>
        @endif
    </div>

    @include('porro::v6.flash')

    @if ($details['crawler'])
        @php($tint = $details['crawler']['real'] ? '22 163 74' : '220 38 38')
        <div v-pre class="rounded-lg border px-4 py-3 text-sm" style="border-color: rgb({{ $tint }} / .45); background: rgb({{ $tint }} / .08)">
            <strong style="color: rgb({{ $tint }})">{{ $details['crawler']['title'] }}.</strong> {{ $details['crawler']['body'] }}
        </div>
    @endif

    @if ($canManage && ! $details['blocked'] && ! $details['safelisted'])
        <div class="flex flex-wrap items-center gap-2">
            @foreach ([[__('Block permanently'), 0, $details['ip'], 'primary'], [__('Block 7 days'), 7, $details['ip'], 'default'], ...($details['range'] ? [[__('Block :range', ['range' => $details['range']]), 7, $details['range'], 'default']] : [])] as [$label, $days, $target, $variant])
                <form method="POST" action="{{ $blockUrl }}" data-porro-confirm="{{ \Porro\Laravel\Activity\Confirm::attribute(\Porro\Laravel\Activity\Confirm::block($target, $days)) }}">
                    @csrf
                    <input type="hidden" name="ip" value="{{ $target }}">
                    <input type="hidden" name="reason" value="{{ $details['reason'] }}">
                    <input type="hidden" name="days" value="{{ $days }}">
                    @if ($days)<input type="hidden" name="suggested" value="1">@endif
                    <ui-button type="submit" variant="{{ $variant }}" text="{{ $label }}"></ui-button>
                </form>
            @endforeach
            <form method="POST" action="{{ $safelistUrl }}" data-porro-confirm="{{ \Porro\Laravel\Activity\Confirm::attribute(\Porro\Laravel\Activity\Confirm::safelist($details['ip'])) }}">
                    @csrf
                    <input type="hidden" name="ip" value="{{ $details['ip'] }}">
                    <input type="hidden" name="label" value="{{ __('From the IP details page') }}">
                    <ui-button type="submit" variant="ghost" text="{{ __('Safelist') }}"></ui-button>
                </form>
        </div>
    @endif

    <ui-panel heading="{{ __('Network') }}">
        <ui-card>
            @forelse ($details['network'] as $label => $value)
                <div class="flex gap-4 py-2 text-sm {{ $loop->first ? '' : 'border-t border-gray-200 dark:border-gray-800' }}">
                    <span class="text-gray-600 dark:text-gray-400" style="width: 8rem; flex: none">{{ __($label) }}</span>
                    <span v-pre class="min-w-0 break-words {{ in_array($label, ['Hostname', 'User agent'], true) ? 'font-mono text-xs' : '' }}">{{ $value }}</span>
                </div>
            @empty
                <p class="text-sm">{{ __('Nothing more is known about this network. Connect Cloudflare for the network and its type.') }}</p>
            @endforelse
        </ui-card>
    </ui-panel>

    <ui-panel heading="{{ __('What it did here') }}" subheading="{{ __('Signals per hour · last 48 hours') }}">
        <ui-card>
            @if ($details['activity'])
                <div class="flex items-end" style="gap: 2px; height: 44px" aria-hidden="true">
                    @foreach ($details['activity']['hours'] as $n)
                        <span style="flex: 1; border-radius: 1px; height: {{ $n ? max(6, (int) round($n / $details['activity']['peak'] * 44)) : 2 }}px; background: {{ $n > 10 ? 'rgb(220 38 38)' : ($n > 0 ? 'rgb(234 128 51)' : 'rgb(127 127 127 / .2)') }}"></span>
                    @endforeach
                </div>
                <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400 mt-1 mb-3"><span>{{ __('48 h ago') }}</span><span>{{ __('now') }}</span></div>
                @foreach ($details['activity']['requests'] as $r)
                    <div class="flex gap-3 py-1.5 font-mono text-xs border-t border-gray-200 dark:border-gray-800">
                        <span class="text-gray-600" style="width: 3rem">{{ $r['method'] }}</span>
                        <span v-pre class="flex-1 min-w-0 break-all">{{ $r['path'] }}</span>
                        <span style="{{ $r['status'] >= 200 && $r['status'] < 300 ? 'color: rgb(220 38 38)' : '' }}" class="text-gray-600">{{ $r['status'] ?: '—' }}</span>
                        <span class="text-gray-600">{{ $r['time'] }}</span>
                    </div>
                @endforeach
            @else
                <p class="text-sm">{{ __('Nothing flagged from this IP in the last 48 hours.') }}</p>
            @endif
        </ui-card>
    </ui-panel>

    <ui-panel heading="{{ __('Reputation') }}" subheading="AbuseIPDB">
        <ui-card>
            @if ($details['reputation'])
                <p class="text-sm"><strong style="{{ $details['reputation']['score'] >= 50 ? 'color: rgb(220 38 38)' : '' }}">{{ $details['reputation']['score'] }}%</strong> {{ __('abuse confidence') }} · {{ trans_choice(':count report|:count reports', $details['reputation']['reports'], ['count' => number_format($details['reputation']['reports'])]) }}@if ($details['reputation']['isp']) · <span v-pre>{{ $details['reputation']['isp'] }}</span>@endif</p>
            @elseif ($details['canCheckReputation'] && $canManage)
                <form method="POST" action="{{ $reputationUrl }}" class="flex items-center gap-3">
                    @csrf
                    <ui-button type="submit" size="sm" text="{{ __('Check on AbuseIPDB') }}"></ui-button>
                    <span class="text-xs text-gray-600 dark:text-gray-400">{{ __('Sends this IP to AbuseIPDB.') }}</span>
                </form>
            @else
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Not checked. Set PORRO_ABUSEIPDB_KEY to check an IP’s reputation on request (it sends the IP to AbuseIPDB).') }}</p>
            @endif
        </ui-card>
    </ui-panel>

    <p class="text-xs text-gray-600 dark:text-gray-400">
        {{ __('Sources') }}: {{ implode(' · ', $details['sources']) }} · {{ $details['fetched'] }}
        @if ($details['attribution']) · <a href="{{ $details['attribution']['url'] }}" target="_blank" rel="noopener" class="underline">{{ $details['attribution']['text'] }}</a>@endif
    </p>
</div>

@include('porro::v6.confirm')
