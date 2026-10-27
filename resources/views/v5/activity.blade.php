{{-- Statamic 5. IPs and paths come from visitors, so everything they touch sits in v-pre. --}}
<div class="flex items-baseline justify-between mb-2">
    <h2 class="font-bold">{{ __('Suspicious activity') }}</h2>
    <span class="text-xs text-gray-600">{{ __('Last :hours hours', ['hours' => $activity['hours']]) }}</span>
</div>
<div class="card mb-6">
    @unless ($activity['recording'])
        <p class="text-sm text-gray-700 mb-4">{{ __('Not recording on this copy of the site: activity is only recorded in production. Set PORRO_ACTIVITY=true to record here.') }}</p>
    @endunless

    <div class="flex flex-wrap -mx-1 mb-4">
        @foreach ($activity['totals'] as $total)
            <div class="w-1/2 md:w-1/4 px-1 mb-2">
                <div class="rounded bg-gray-200 px-3 py-2">
                    <div class="text-xl font-bold">{{ number_format($total['value']) }}</div>
                    <div class="text-xs text-gray-700">{{ $total['label'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if (empty($activity['flagged']))
        <p class="text-sm">{{ __('Nothing suspicious in the last :hours hours.', ['hours' => $activity['hours']]) }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('IP') }}</th>
                        <th>{{ __('Country') }}</th>
                        <th>{{ __('Signals') }}</th>
                        <th>{{ __('Last request') }}</th>
                        <th>{{ __('Last seen') }}</th>
                        @if ($canManage)<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($activity['flagged'] as $ip)
                        <tr>
                            <td v-pre class="font-mono text-xs whitespace-nowrap"><a href="{{ cp_route('porro.ip', $ip['ip']) }}" class="text-blue hover:text-blue-800">{{ $ip['ip'] }}</a>
                                @foreach ($ip['badges'] as $badge)<span class="badge-pill-sm ml-1 {{ ['danger' => 'text-red-500', 'warning' => 'text-orange'][$badge['tone']] ?? '' }}">{{ $badge['label'] }}</span>@endforeach</td>
                            <td v-pre class="whitespace-nowrap" title="{{ $ip['country']['code'] ?? '' }}">{{ $ip['country']['label'] ?? '—' }}</td>
                            <td>
                                @foreach ($ip['signals'] as $signal)
                                    <span class="badge-pill-sm">{{ $signal['label'] }} {{ $signal['count'] }}</span>
                                @endforeach
                            </td>
                            <td v-pre class="font-mono text-xs" style="overflow-wrap: anywhere">{{ $ip['request'] }}</td>
                            <td class="whitespace-nowrap text-gray-700">{{ $ip['seen'] }}</td>
                            @if ($canManage)
                                <td class="text-right whitespace-nowrap">
                                    @if ($ip['blocked'])
                                        <span class="text-red-500 text-sm">{{ __('Blocked') }}</span>
                                    @else
                                    <form method="POST" action="{{ $blockUrl }}" class="inline" onsubmit="return confirm({{ \Illuminate\Support\Js::from(\Porro\Laravel\Activity\Confirm::block($ip['ip'], 7)) }})">
                                        @csrf
                                        <input type="hidden" name="ip" value="{{ $ip['ip'] }}">
                                        <input type="hidden" name="reason" value="{{ $ip['reason'] }}">
                                        <input type="hidden" name="days" value="7">
                                        <input type="hidden" name="suggested" value="1">
                                        <button type="submit" class="btn btn-sm">{{ __('Block 7 days') }}</button>
                                    </form>
                                    <form method="POST" action="{{ $blockUrl }}" class="inline" onsubmit="return confirm({{ \Illuminate\Support\Js::from(\Porro\Laravel\Activity\Confirm::block($ip['ip'])) }})">
                                        @csrf
                                        <input type="hidden" name="ip" value="{{ $ip['ip'] }}">
                                        <input type="hidden" name="reason" value="{{ $ip['reason'] }}">
                                        <button type="submit" class="btn btn-sm">{{ __('Block permanently') }}</button>
                                    </form>
                                    <form method="POST" action="{{ $safelistUrl }}" class="inline" onsubmit="return confirm({{ \Illuminate\Support\Js::from(\Porro\Laravel\Activity\Confirm::safelist($ip['ip'])) }})">
                                        @csrf
                                        <input type="hidden" name="ip" value="{{ $ip['ip'] }}">
                                        <input type="hidden" name="label" value="{{ __('From the activity table') }}">
                                        <button type="submit" class="text-blue hover:text-blue-800 text-sm ml-1">{{ __('Safelist') }}</button>
                                    </form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <p class="text-sm mt-4">
        <a href="{{ $blockingUrl }}" class="text-blue hover:text-blue-800">{{ trans_choice(':count IP blocked|:count IPs blocked', $activity['blocked'], ['count' => $activity['blocked']]) }} · {{ trans_choice(':count safelisted|:count safelisted', $activity['safelisted'], ['count' => $activity['safelisted']]) }} &rarr;</a>
    </p>
    <p class="text-xs text-gray-700 mt-2">
        {{ __('IPs are kept on this site for 48 hours and never sent anywhere. Statamic’s static cache serves pages without reaching the app, so traffic counts run low; probes and logins are always caught.') }}
        @if ($activity['countries'])
            <a href="{{ $activity['countries']['url'] }}" target="_blank" rel="noopener" class="text-blue">{{ $activity['countries']['attribution'] }}</a>.
        @endif
    </p>
</div>
