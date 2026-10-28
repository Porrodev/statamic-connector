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
            @if ($details['protected'] ?? null) · {{ __('always protected: :list', ['list' => $details['protected']]) }} @endif
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

    @if ($canManage && ! $details['blocked'] && ! $details['safelisted'] && ! ($details['protected'] ?? null))
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

    {{-- Paths, hosts and user agents come from the visitor: v-pre, so Vue never reads them as templates. --}}
    <ui-panel heading="{{ __('What it did here') }}" subheading="{{ __('Requests per hour · last 48 hours') }}">
        <ui-card>
            @if ($details['activity'])
                @php($did = $details['activity'])
                <div class="flex items-end" style="gap: 2px; height: 44px" aria-hidden="true">
                    @foreach ($did['hours'] as $n)
                        <span style="flex: 1; border-radius: 1px; height: {{ $n ? max(6, (int) round($n / $did['peak'] * 44)) : 2 }}px; background: {{ $n > 10 ? 'rgb(220 38 38)' : ($n > 0 ? 'rgb(234 128 51)' : 'rgb(127 127 127 / .2)') }}"></span>
                    @endforeach
                </div>
                <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400 mt-1 mb-3"><span>{{ __('48 h ago') }}</span><span>{{ __('now') }}</span></div>
                @foreach ($did['summary'] as $label => $value)
                    <div class="flex gap-4 py-2 text-sm border-t border-gray-200 dark:border-gray-800">
                        <span class="text-gray-600 dark:text-gray-400" style="width: 8rem; flex: none">{{ __($label) }}</span>
                        <span v-pre class="min-w-0 break-words">{{ $value }}</span>
                    </div>
                @endforeach
                @if ($did['direct'])
                    <p class="text-xs mt-3" style="color: rgb(217 119 6)">{{ __('Some of its requests came straight to the server’s own address, not through Cloudflare. Blocking at Cloudflare won’t stop those: block in the app or in nginx, or let only Cloudflare reach the server at its firewall.') }}</p>
                @endif
                @unless ($did['flagged'])
                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-3">{{ __('Recorded, but under the thresholds that flag an IP. Only its suspicious requests are counted until it’s flagged.') }}</p>
                @endunless
            @else
                <p class="text-sm">{{ __('Nothing recorded from this IP in the last 48 hours.') }}</p>
            @endif
        </ui-card>
    </ui-panel>

    @if ($details['activity'] && $details['activity']['paths'])
        <ui-panel heading="{{ __('Paths it asked for') }}" subheading="{{ __('Requests · last answer') }}">
            <ui-card>
                @foreach ($details['activity']['paths'] as $p)
                    <div class="flex gap-3 py-1.5 font-mono text-xs {{ $loop->first ? '' : 'border-t border-gray-200 dark:border-gray-800' }}">
                        <span v-pre class="flex-1 min-w-0 break-all">{{ $p['path'] }}</span>
                        <span class="text-gray-600">×{{ number_format($p['count']) }}</span>
                        <span style="width: 2.6rem; text-align: right; {{ $p['status'] >= 200 && $p['status'] < 300 ? 'color: rgb(220 38 38)' : '' }}" class="text-gray-600">{{ $p['status'] ?: '—' }}</span>
                    </div>
                @endforeach
                @if ($details['activity']['otherPaths'])
                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">{{ trans_choice('…and :count more request to other paths.|…and :count more requests to other paths.', $details['activity']['otherPaths'], ['count' => number_format($details['activity']['otherPaths'])]) }}</p>
                @endif
            </ui-card>
        </ui-panel>
    @endif

    @if ($details['activity'] && $details['activity']['agents'])
        <ui-panel heading="{{ __('User agents') }}">
            <ui-card>
                @foreach ($details['activity']['agents'] as $a)
                    <div class="flex gap-3 py-1.5 font-mono text-xs {{ $loop->first ? '' : 'border-t border-gray-200 dark:border-gray-800' }}">
                        <span v-pre class="flex-1 min-w-0 break-all">{{ $a['agent'] }}</span>
                        <span class="text-gray-600">×{{ number_format($a['count']) }}</span>
                    </div>
                @endforeach
            </ui-card>
        </ui-panel>
    @endif

    @if ($details['activity'] && $details['activity']['requests'])
        <ui-panel heading="{{ __('Latest requests') }}">
            <ui-card>
                @foreach ($details['activity']['requests'] as $r)
                    <div class="flex gap-3 py-1.5 font-mono text-xs {{ $loop->first ? '' : 'border-t border-gray-200 dark:border-gray-800' }}">
                        <span class="text-gray-600" style="width: 3rem">{{ $r['method'] }}</span>
                        <span v-pre class="flex-1 min-w-0 break-all">{{ $r['path'] }}</span>
                        <span style="{{ $r['status'] >= 200 && $r['status'] < 300 ? 'color: rgb(220 38 38)' : '' }}" class="text-gray-600">{{ $r['status'] ?: '—' }}</span>
                        <span class="text-gray-600">{{ $r['time'] }}</span>
                    </div>
                @endforeach
            </ui-card>
        </ui-panel>
    @endif

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
