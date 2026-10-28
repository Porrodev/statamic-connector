{{-- Statamic 6. Cloudflare's errors sit in v-pre, so Vue never reads them as templates. --}}
@php
    $code = 'text-xs bg-gray-50 dark:bg-gray-900 rounded-lg p-3 overflow-x-auto';
    $state = fn (bool $ok) => $ok ? '✓ '.__('Done') : __('To do');
@endphp
<div class="max-w-page mx-auto space-y-6" v-cloak>
    @include('porro::v6.flash')

    <div>
        <a href="{{ $blockingUrl }}" class="text-sm underline underline-offset-2">← {{ __('Blocking') }}</a>
        <ui-header title="{{ __('Set up Cloudflare') }}" icon="{{ $icon }}"></ui-header>
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('For sites behind Cloudflare: blocked IPs are stopped at Cloudflare’s edge, before they reach your server. Three steps, once for the site.') }}</p>
    </div>

    @if ($cloudflare['configured'] && ! $cloudflare['production'])
        <ui-card><p class="text-sm">{{ __('This copy isn’t production, so it won’t sync the blocklist. You can still connect here to check the token.') }}</p></ui-card>
    @endif

    <ui-panel heading="{{ __('1 · Create a Cloudflare API token') }}" subheading="{{ $state($steps['token']) }}">
        <ui-card>
            <p class="text-sm mb-2">{{ __('In Cloudflare, open') }} <a href="{{ $tokensUrl }}" target="_blank" rel="noopener" class="underline underline-offset-2">{{ __('My Profile → API Tokens') }}</a>{{ __(', then Create Token → Create Custom Token. It needs two permission policies: one for the site’s domain, one for your account.') }}</p>
            @foreach ($policies as $i => $policy)
                <p v-pre class="text-sm mt-4 mb-1"><strong>{{ __('Policy :n', ['n' => $i + 1]) }}</strong> · {{ __('scope it to') }} {{ $i === 0 ? __('Specified Domains → :host', ['host' => $host ?: __('this site’s domain')]) : __($policy['scope']) }}@if ($i > 0) {{ __('(Add policy, then pick Entire Account)') }}@endif</p>
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs text-gray-600 dark:text-gray-400"><th class="py-1 pr-4 font-medium">{{ __('Search for') }}</th><th class="py-1 pr-4 font-medium">{{ __('Tick') }}</th><th class="py-1 font-medium">{{ __('Access') }}</th></tr></thead>
                    <tbody>
                        @foreach ($policy['permissions'] as $p)
                            <tr class="border-t border-gray-200 dark:border-gray-800"><td class="py-2 pr-4 font-mono text-xs">{{ $p['search'] }}</td><td class="py-2 pr-4">{{ $p['tick'] }}@if ($p['group'])<span class="text-xs text-gray-600 dark:text-gray-400"> · {{ __('under :group', ['group' => $p['group']]) }}</span>@endif</td><td class="py-2">{{ $p['access'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-3">{{ __('Create the token and copy it: Cloudflare only shows it once.') }}</p>
        </ui-card>
    </ui-panel>

    <ui-panel heading="{{ __('2 · Add it to the site’s environment') }}" subheading="{{ $state($steps['token']) }}">
        <ui-card>
            <p class="text-sm mb-2">{{ __('Paste the token after the = in .env, or on Forge, the site’s Environment tab. It stays in the environment, never the database.') }}</p>
            <div class="flex items-start gap-2"><pre v-pre class="{{ $code }} flex-1">{{ $envLine }}your-token-here</pre><ui-button size="sm" data-porro-copy="{{ $envLine }}" text="{{ __('Copy') }}"></ui-button></div>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">{{ $steps['token'] ? __('Token found.') : __('This page ticks this off once the site can see the token. If you cache config, run php please config:cache after saving it.') }}</p>
        </ui-card>
    </ui-panel>

    <ui-panel heading="{{ __('3 · Connect') }}" subheading="{{ $state($steps['connected']) }}">
        <ui-card>
            <div class="text-sm rounded-lg p-3 mb-3" style="border: 1px solid rgb(245 158 11 / .45); background: rgb(245 158 11 / .08)">
                <strong>{{ __('One list for the whole Cloudflare account.') }}</strong>
                {{ __('Cloudflare keeps IP lists per account, not per domain, so porro_blocklist is shared by every site you connect on this account. An IP blocked on one of them is blocked on all of them. Each site only adds and removes its own entries, and you’ll find the list under Manage account → Configurations → Lists.') }}
                {{ __('Sites that mustn’t share blocks, such as different clients, need their own Cloudflare accounts.') }}
            </div>
            @if ($cloudflare['connected'])
                <p v-pre class="text-sm">{{ __('Connected to :zone. Blocks go to the porro_blocklist list, with one rule blocking it next to your existing rules.', ['zone' => $cloudflare['zone']]) }}</p>
                <p v-pre class="text-xs mt-1 {{ $cloudflare['error'] ? '' : 'text-gray-600 dark:text-gray-400' }}" @if ($cloudflare['error']) style="color: rgb(220 38 38)" @endif>
                    @if ($cloudflare['error']) {{ $cloudflare['error'] }}
                    @elseif ($cloudflare['synced']) {{ __('Last synced :ago. It syncs after every change and on the scheduled task.', ['ago' => $cloudflare['synced']]) }}
                    @else {{ __('Waiting for the first sync.') }}
                    @endif
                </p>
            @else
                <p v-pre class="text-sm mb-3">{{ __('Finds the zone for :host, creates the porro_blocklist list if the account doesn’t have it yet, and adds one rule blocking it, next to your existing rules, never replacing them.', ['host' => $host ?: __('this site')]) }}</p>
                @if ($canManage)
                    <form method="POST" action="{{ $connectUrl }}">
                        @csrf
                        <ui-button type="submit" variant="primary" text="{{ __('Connect to Cloudflare') }}" @disabled(! $steps['token'])></ui-button>
                    </form>
                @endif
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">{{ __('Or on the server:') }} <code>{{ $command }}</code></p>
                @if ($cloudflare['error'])
                    <p v-pre class="text-xs mt-1" style="color: rgb(220 38 38)">{{ $cloudflare['error'] }}</p>
                @endif
            @endif
        </ui-card>
    </ui-panel>
</div>
