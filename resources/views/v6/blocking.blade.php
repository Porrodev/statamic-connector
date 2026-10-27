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
                        <p class="text-gray-600 dark:text-gray-400">{{ __('Blocked at the edge, before traffic reaches the server · zone :zone · list porro_blocklist', ['zone' => $cloudflare['zone']]) }}@if ($cloudflare['result'] && $cloudflare['result']['shared']) · {{ trans_choice(':count already blocked by another site|:count already blocked by other sites', $cloudflare['result']['shared'], ['count' => $cloudflare['result']['shared']]) }}@endif</p>
                    @elseif ($cloudflare['configured'])
                        <p class="text-gray-600 dark:text-gray-400 mb-2">{{ __('Run this on the server to find the zone, create the porro_blocklist list and add the rule next to your existing ones:') }}</p>
                        <pre v-pre class="text-xs bg-gray-50 dark:bg-gray-900 rounded-lg p-3 overflow-x-auto">php please porro:cloudflare connect</pre>
                    @else
                        <p class="text-gray-600 dark:text-gray-400 mb-2">{{ __('Blocks at Cloudflare’s edge for sites behind it. Create a token with Account Filter Lists Edit, Zone WAF Edit and Zone Read, then:') }}</p>
                        <pre v-pre class="text-xs bg-gray-50 dark:bg-gray-900 rounded-lg p-3 overflow-x-auto">PORRO_CLOUDFLARE_TOKEN=your-token-here
php please porro:cloudflare connect</pre>
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
                            {{ __('For sites not behind Cloudflare, or as well as it. Two one-time steps on each server.') }}
                            <a href="{{ $nginxUrl }}" class="underline underline-offset-2">{{ __('Set up nginx') }} →</a>
                        @endif
                    </p>
                </li>
            </ul>
        </ui-card>
    </ui-panel>

    <ui-panel heading="{{ __('Blocklist') }}" subheading="{{ trans_choice(':count entry|:count entries', count($blocks), ['count' => count($blocks)]) }}">
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
        </ui-card>
    </ui-panel>

    <ui-panel heading="{{ __('Always protected') }}" subheading="{{ __('Can’t be blocked') }}">
        <ui-card>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">{{ __('Infrastructure that has to reach this site. Blocks that overlap these are refused, and removed if a list changes.') }}</p>
            <ul class="divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                @foreach ($protected as $row)
                    <li class="py-2 flex justify-between"><span>{{ $row['label'] }}</span><span class="text-gray-600 dark:text-gray-400">{{ trans_choice(':count range|:count ranges', $row['count'], ['count' => $row['count']]) }}</span></li>
                @endforeach
            </ul>
        </ui-card>
    </ui-panel>
</div>
