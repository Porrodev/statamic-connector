{{-- Statamic 6. Notes and labels are typed by people, so they sit in v-pre too. --}}
@php($field = 'w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm')
<div class="max-w-page mx-auto space-y-6" v-cloak>
    <ui-header title="{{ __('Blocking') }}" icon="{{ $icon }}">
        <template #actions>
            <ui-button href="{{ $healthUrl }}" variant="ghost" text="{{ __('Site Health') }}"></ui-button>
        </template>
    </ui-header>

    @include('porro::v6.flash')

    @if ($failed)
        <ui-card>
            <p class="text-sm">{{ __('The blocklist tables aren’t there yet. Run php please migrate (or php artisan migrate) on this site.') }}</p>
        </ui-card>
    @endif

    <ui-panel heading="{{ __('Where blocks are enforced') }}">
        <ui-card>
            <ul class="divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                <li class="py-3 first:pt-0">
                    <div class="flex items-center gap-2 font-medium">{{ __('In the app') }}
                        <ui-badge size="sm" color="{{ $enforcement['app'] ? 'green' : 'default' }}" text="{{ $enforcement['app'] ? __('Active') : __('Off') }}"></ui-badge>
                    </div>
                    <p class="text-gray-600 dark:text-gray-400">{{ __('Blocked IPs get a 403 before Statamic handles the request. Turn off with PORRO_BLOCK_APP=false.') }}</p>
                </li>
                <li class="py-3">
                    <div class="flex items-center gap-2 font-medium">Cloudflare WAF
                        @if (! $cloudflare['configured'])
                            <ui-badge size="sm" text="{{ __('Not set up') }}"></ui-badge>
                        @elseif (! $cloudflare['connected'])
                            <ui-badge size="sm" color="amber" text="{{ __('Token set, not connected') }}"></ui-badge>
                        @elseif ($cloudflare['error'])
                            <ui-badge size="sm" color="red" text="{{ __('Sync failed') }}"></ui-badge>
                        @elseif (! $cloudflare['production'])
                            <ui-badge size="sm" text="{{ __('Production only') }}"></ui-badge>
                        @elseif ($cloudflare['synced'])
                            <ui-badge size="sm" color="green" text="{{ __('Synced :ago', ['ago' => $cloudflare['synced']]) }}"></ui-badge>
                        @else
                            <ui-badge size="sm" color="amber" text="{{ __('Waiting for the first sync') }}"></ui-badge>
                        @endif
                    </div>
                    @if ($cloudflare['connected'])
                        <p class="text-gray-600 dark:text-gray-400">{{ __('Blocked at the edge, before traffic reaches the server · zone :zone · list porro_blocklist', ['zone' => $cloudflare['zone']]) }}@if ($cloudflare['result'] && $cloudflare['result']['shared']) · {{ trans_choice(':count already blocked by another site|:count already blocked by other sites', $cloudflare['result']['shared'], ['count' => $cloudflare['result']['shared']]) }}@endif · <a href="{{ $cloudflareUrl }}" class="underline underline-offset-2">{{ __('Setup') }} →</a></p>
                    @else
                        <p class="text-gray-600 dark:text-gray-400">{{ __('For sites behind Cloudflare: blocks at Cloudflare’s edge, before traffic reaches the server. Three steps, and no terminal needed.') }} <a href="{{ $cloudflareUrl }}" class="underline underline-offset-2">{{ $cloudflare['configured'] ? __('Finish setting up') : __('Set up Cloudflare') }} →</a></p>
                    @endif
                    @if ($cloudflare['error'])
                        <p v-pre class="mt-1" style="color: rgb(220 38 38)">{{ $cloudflare['error'] }}</p>
                    @endif
                </li>
                <li class="py-3 last:pb-0">
                    <div class="flex items-center gap-2 font-medium">nginx
                        @php($loaded = collect($enforcement['servers'])->where('ok', true)->count())
                        @if (! $enforcement['nginx'])
                            <ui-badge size="sm" text="{{ __('Not set up') }}"></ui-badge>
                        @elseif (! $enforcement['production'])
                            <ui-badge size="sm" text="{{ __('Production only') }}"></ui-badge>
                        @elseif ($enforcement['servers'] && $loaded === count($enforcement['servers']))
                            <ui-badge size="sm" color="green" text="{{ __('Loaded') }}"></ui-badge>
                        @else
                            <ui-badge size="sm" color="amber" text="{{ __('Setting up') }}"></ui-badge>
                        @endif
                    </div>
                    <p class="text-gray-600 dark:text-gray-400">
                        @if ($enforcement['servers'])
                            {{ trans_choice(':loaded of :count server loaded|:loaded of :count servers loaded', count($enforcement['servers']), ['loaded' => $loaded, 'count' => count($enforcement['servers'])]) }} ·
                            <a href="{{ $nginxUrl }}" class="underline underline-offset-2">{{ __('Setup and servers') }} →</a>
                        @else
                            {{ __('For sites not behind Cloudflare, or as well as it. Three one-time steps: one for the site, two on each server.') }}
                            <a href="{{ $nginxUrl }}" class="underline underline-offset-2">{{ __('Set up nginx') }} →</a>
                        @endif
                    </p>
                </li>
            </ul>
        </ui-card>
    </ui-panel>

    <ui-panel heading="{{ __('Blocklist') }}" subheading="{{ trans_choice(':count entry|:count entries', $blockCount, ['count' => number_format($blockCount)]) }}">
        <ui-card>
            @if ($canManage)
                <form method="POST" action="{{ $urls['block'] }}" data-form="block" class="flex flex-wrap items-center gap-2 mb-4">
                    @csrf
                    <input type="hidden" name="form" value="block">
                    <input name="ip" required value="{{ old('form') === 'block' ? old('ip') : '' }}" placeholder="{{ __('IP or range, e.g. 203.0.113.0/24') }}" class="{{ $field }}" style="max-width: 18rem" aria-label="{{ __('IP or range') }}">
                    <input name="reason" value="{{ old('form') === 'block' ? old('reason') : '' }}" placeholder="{{ __('Reason') }}" class="{{ $field }} flex-1" style="min-width: 10rem" aria-label="{{ __('Reason') }}">
                    <select name="days" class="{{ $field }}" style="max-width: 10rem" aria-label="{{ __('Expires') }}">
                        <option value="7">{{ __('For 7 days') }}</option>
                        <option value="30">{{ __('For 30 days') }}</option>
                        <option value="0">{{ __('Never expires') }}</option>
                    </select>
                    <ui-button type="submit" variant="primary" text="{{ __('Block') }}"></ui-button>
                </form>
            @endif
            @include('porro::v6.entries', ['entries' => $blocks, 'remove' => $urls['unblock'], 'empty' => __('Nothing is blocked.')])
            @if ($blockCount > count($blocks))<p class="mt-3"><a href="{{ $blocklistUrl }}" class="text-sm underline underline-offset-2">{{ __('Show all :count, with search →', ['count' => number_format($blockCount)]) }}</a></p>@elseif ($blockCount > 0)<p class="mt-3"><a href="{{ $blocklistUrl }}" class="text-sm underline underline-offset-2">{{ __('Search the blocklist →') }}</a></p>@endif
        </ui-card>
    </ui-panel>

    <ui-panel id="safelist" heading="{{ __('Safelist') }}" subheading="{{ __('Never blocked or flagged') }}">
        <ui-card>
            @if ($canManage)
                <form method="POST" action="{{ $urls['safelist'] }}" data-form="safelist" class="flex flex-wrap items-center gap-2 mb-4">
                    @csrf
                    <input type="hidden" name="form" value="safelist">
                    <input name="ip" required value="{{ old('form') === 'safelist' ? old('ip') : $prefill }}" placeholder="{{ __('IP or range') }}" class="{{ $field }}" style="max-width: 18rem" aria-label="{{ __('IP or range') }}">
                    <input name="label" required value="{{ old('form') === 'safelist' ? old('label') : '' }}" placeholder="{{ __('Label, e.g. 1994 office') }}" class="{{ $field }} flex-1" style="min-width: 10rem" aria-label="{{ __('Label') }}">
                    <label class="flex items-center gap-1 text-xs text-gray-600"><input type="checkbox" name="confirm_wide" value="1"> {{ __('Wide range is OK') }}</label>
                    <ui-button type="submit" variant="primary" text="{{ __('Add') }}"></ui-button>
                </form>
            @endif
            @include('porro::v6.entries', ['entries' => $safe, 'remove' => $urls['unsafelist'], 'empty' => __('Nothing is safelisted.')])
            @if ($safeCount > count($safe))<p class="mt-3"><a href="{{ $safelistUrl }}" class="text-sm underline underline-offset-2">{{ __('Show all :count, with search →', ['count' => number_format($safeCount)]) }}</a></p>@endif
        </ui-card>
    </ui-panel>

    <ui-panel heading="{{ __('Always protected') }}" subheading="{{ __('Can’t be blocked') }}">
        <ui-card>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">{{ __('Addresses that have to reach this site. Blocks that overlap these are refused, and removed if a list changes. Open one to see its addresses and why.') }}</p>
            <div class="divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                @foreach ($protected as $row)
                    <details class="group py-2">
                        <summary class="flex justify-between gap-4 cursor-pointer list-none">
                            <span><span class="inline-block text-gray-500 transition-transform group-open:rotate-90">›</span> {{ $row['label'] }}</span>
                            @if ($row['on'])
                                <span class="text-gray-600 dark:text-gray-400">{{ trans_choice(':count range|:count ranges', $row['count'], ['count' => $row['count']]) }}</span>
                            @else
                                <ui-badge size="sm" text="{{ __('Switched off') }}"></ui-badge>
                            @endif
                        </summary>
                        <div class="mt-2 space-y-2 text-xs">
                            <p>{{ $row['why'] }}</p>
                            @if ($row['from'])
                                <p class="text-gray-600 dark:text-gray-400">{{ __('From') }}: @foreach ($row['from'] as $url)<a href="{{ $url }}" target="_blank" rel="noopener" class="underline">{{ parse_url($url, PHP_URL_HOST) }}{{ parse_url($url, PHP_URL_PATH) }}</a>@if (! $loop->last) · @endif @endforeach</p>
                            @endif
                            @if ($row['env'])
                                <p class="text-gray-600 dark:text-gray-400">{{ $row['on'] ? __('Not needed here? Switch it off with :setting.', ['setting' => $row['env'].'=false']) : __('Switched off with :setting. Remove it to protect these again.', ['setting' => $row['env'].'=false']) }}</p>
                            @endif
                            @if ($row['ranges'])
                                <div v-pre class="font-mono rounded-lg bg-gray-50 dark:bg-gray-900 p-3" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(24ch, 1fr)); gap: 2px 16px; max-height: 240px; overflow-y: auto;">@foreach ($row['ranges'] as $range)<span>{{ $range }}</span>@endforeach</div>
                            @endif
                        </div>
                    </details>
                @endforeach
            </div>
        </ui-card>
    </ui-panel>
</div>
