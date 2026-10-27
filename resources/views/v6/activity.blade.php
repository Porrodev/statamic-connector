{{-- Statamic 6. IPs and paths come from visitors, so everything they touch sits in v-pre. --}}
<ui-panel heading="{{ __('Suspicious activity') }}" subheading="{{ __('Last :hours hours', ['hours' => $activity['hours']]) }}">
    <ui-card>
        @unless ($activity['recording'])
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ __('Not recording on this copy of the site: activity is only recorded in production. Set PORRO_ACTIVITY=true to record here.') }}</p>
        @endunless

        <div class="grid gap-3 mb-4" style="grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr))">
            @foreach ($activity['totals'] as $total)
                <div class="rounded-lg bg-gray-50 dark:bg-gray-900 px-4 py-3">
                    <div class="text-2xl font-semibold">{{ number_format($total['value']) }}</div>
                    <div class="text-xs text-gray-600 dark:text-gray-400">{{ $total['label'] }}</div>
                </div>
            @endforeach
        </div>

        @if (empty($activity['flagged']))
            <p class="text-sm">{{ __('Nothing suspicious in the last :hours hours.', ['hours' => $activity['hours']]) }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-gray-500">
                            <th class="py-2 pr-4 font-medium">{{ __('IP') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('Country') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('Signals') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('Last request') }}</th>
                            <th class="py-2 pr-4 font-medium">{{ __('Last seen') }}</th>
                            @if ($canManage)<th class="py-2"></th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($activity['flagged'] as $ip)
                            <tr>
                                <td v-pre class="py-2 pr-4 font-mono text-xs whitespace-nowrap">{{ $ip['ip'] }}</td>
                                <td v-pre class="py-2 pr-4 whitespace-nowrap" title="{{ $ip['country']['code'] ?? '' }}">{{ $ip['country']['label'] ?? '—' }}</td>
                                <td class="py-2 pr-4">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($ip['signals'] as $signal)
                                            <ui-badge size="sm" text="{{ $signal['label'] }} {{ $signal['count'] }}"></ui-badge>
                                        @endforeach
                                    </div>
                                </td>
                                <td v-pre class="py-2 pr-4 font-mono text-xs break-all">{{ $ip['request'] }}</td>
                                <td class="py-2 pr-4 whitespace-nowrap text-gray-600 dark:text-gray-400">{{ $ip['seen'] }}</td>
                                @if ($canManage)
                                    <td class="py-2">
                                        <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                                            @if ($ip['blocked'])
                                                <ui-badge size="sm" color="red" text="{{ __('Blocked') }}"></ui-badge>
                                            @else
                                            <form method="POST" action="{{ $blockUrl }}">
                                                @csrf
                                                <input type="hidden" name="ip" value="{{ $ip['ip'] }}">
                                                <input type="hidden" name="reason" value="{{ $ip['reason'] }}">
                                                <input type="hidden" name="days" value="7">
                                                <input type="hidden" name="suggested" value="1">
                                                <ui-button type="submit" size="sm" text="{{ __('Block 7 days') }}"></ui-button>
                                            </form>
                                            <form method="POST" action="{{ $blockUrl }}">
                                                @csrf
                                                <input type="hidden" name="ip" value="{{ $ip['ip'] }}">
                                                <input type="hidden" name="reason" value="{{ $ip['reason'] }}">
                                                <ui-button type="submit" size="sm" text="{{ __('Block permanently') }}"></ui-button>
                                            </form>
                                            <ui-button size="sm" variant="ghost" href="{{ $blockingUrl }}?safelist={{ urlencode($ip['ip']) }}#safelist" text="{{ __('Safelist') }}"></ui-button>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <p class="text-sm mt-4">
            <a href="{{ $blockingUrl }}" class="underline underline-offset-2">{{ trans_choice(':count IP blocked|:count IPs blocked', $activity['blocked'], ['count' => $activity['blocked']]) }} · {{ trans_choice(':count safelisted|:count safelisted', $activity['safelisted'], ['count' => $activity['safelisted']]) }}</a>
        </p>
        <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">
            {{ __('IPs are kept on this site for 48 hours and never sent anywhere. Statamic’s static cache serves pages without reaching the app, so traffic counts run low; probes and logins are always caught.') }}
            @if ($activity['countries'])
                <a href="{{ $activity['countries']['url'] }}" target="_blank" rel="noopener" class="underline underline-offset-2">{{ $activity['countries']['attribution'] }}</a>.
            @endif
        </p>
    </ui-card>
</ui-panel>
