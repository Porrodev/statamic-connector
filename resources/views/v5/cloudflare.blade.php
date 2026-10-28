{{-- Statamic 5. Cloudflare's errors sit in v-pre, so Vue never reads them as templates. --}}
@php
    $tone = fn (bool $ok) => $ok ? 'text-green-600' : 'text-gray-700';
    $state = fn (bool $ok) => $ok ? __('Done') : __('To do');
@endphp
@include('porro::v5.flash')

<header class="mb-6">
    <a href="{{ $blockingUrl }}" class="text-sm text-blue hover:text-blue-800">&larr; {{ __('Blocking') }}</a>
    <h1 class="flex items-center mt-1"><span class="h-6 w-6 mr-2 text-gray-800">{!! $icon !!}</span>{{ __('Set up Cloudflare') }}</h1>
    <p class="text-sm text-gray-700 mt-2">{{ __('For sites behind Cloudflare: blocked IPs are stopped at Cloudflare’s edge, before they reach your server. Three steps, once for the site.') }}</p>
</header>

@if ($cloudflare['configured'] && ! $cloudflare['production'])
    <div class="card mb-6 text-sm">{{ __('This copy isn’t production, so it won’t sync the blocklist. You can still connect here to check the token.') }}</div>
@endif

<h2 class="mb-2 font-bold flex justify-between">{{ __('1 · Create a Cloudflare API token') }} <span class="font-normal text-sm {{ $tone($steps['token']) }}">{{ $state($steps['token']) }}</span></h2>
<div class="card mb-6 text-sm">
    <p class="text-gray-700 mb-2">{{ __('In Cloudflare, open') }} <a href="{{ $tokensUrl }}" target="_blank" rel="noopener" class="text-blue hover:text-blue-800">{{ __('My Profile → API Tokens') }}</a>{{ __(', then Create Token → Create Custom Token. It needs two permission policies: one for the site’s domain, one for your account.') }}</p>
    @foreach ($policies as $i => $policy)
        <p v-pre class="mt-4 mb-1"><strong>{{ __('Policy :n', ['n' => $i + 1]) }}</strong> · {{ __('scope it to') }} {{ $i === 0 ? __('Specified Domains → :host', ['host' => $host ?: __('this site’s domain')]) : __($policy['scope']) }}@if ($i > 0) {{ __('(Add policy, then pick Entire Account)') }}@endif</p>
        <table class="data-table">
            <thead><tr><th>{{ __('Search for') }}</th><th>{{ __('Tick') }}</th><th>{{ __('Access') }}</th></tr></thead>
            <tbody>
                @foreach ($policy['permissions'] as $p)
                    <tr><td class="font-mono text-xs">{{ $p['search'] }}</td><td>{{ $p['tick'] }}@if ($p['group'])<span class="text-xs text-gray-700"> · {{ __('under :group', ['group' => $p['group']]) }}</span>@endif</td><td>{{ $p['access'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
    <p class="text-xs text-gray-700 mt-3">{{ __('Create the token and copy it: Cloudflare only shows it once.') }}</p>
</div>

<h2 class="mb-2 font-bold flex justify-between">{{ __('2 · Add it to the site’s environment') }} <span class="font-normal text-sm {{ $tone($steps['token']) }}">{{ $state($steps['token']) }}</span></h2>
<div class="card mb-6 text-sm">
    <p class="text-gray-700 mb-2">{{ __('Paste the token after the = in .env, or on Forge, the site’s Environment tab. It stays in the environment, never the database.') }}</p>
    <div class="flex items-start"><pre v-pre class="text-xs bg-gray-200 rounded p-2 overflow-x-auto flex-1">{{ $envLine }}your-token-here</pre><button type="button" class="btn btn-sm ml-2" data-porro-copy="{{ $envLine }}">{{ __('Copy') }}</button></div>
    <p class="text-xs text-gray-700 mt-2">{{ $steps['token'] ? __('Token found.') : __('This page ticks this off once the site can see the token. If you cache config, run php please config:cache after saving it.') }}</p>
</div>

<h2 class="mb-2 font-bold flex justify-between">{{ __('3 · Connect') }} <span class="font-normal text-sm {{ $tone($steps['connected']) }}">{{ $state($steps['connected']) }}</span></h2>
<div class="card mb-6 text-sm">
    <div class="rounded p-3 mb-3" style="border: 1px solid rgb(245 158 11 / .45); background: rgb(245 158 11 / .08)">
        <strong>{{ __('One list for the whole Cloudflare account.') }}</strong>
        {{ __('Cloudflare keeps IP lists per account, not per domain, so porro_blocklist is shared by every site you connect on this account. An IP blocked on one of them is blocked on all of them. Each site only adds and removes its own entries, and you’ll find the list under Manage account → Configurations → Lists.') }}
        {{ __('Sites that mustn’t share blocks, such as different clients, need their own Cloudflare accounts.') }}
    </div>
    @if ($cloudflare['connected'])
        <p v-pre>{{ __('Connected to :zone. Blocks go to the porro_blocklist list, with one rule blocking it next to your existing rules.', ['zone' => $cloudflare['zone']]) }}</p>
        <p v-pre class="text-xs mt-1 {{ $cloudflare['error'] ? 'text-red-500' : 'text-gray-700' }}">
            @if ($cloudflare['error']) {{ $cloudflare['error'] }}
            @elseif ($cloudflare['synced']) {{ __('Last synced :ago. It syncs after every change and on the scheduled task.', ['ago' => $cloudflare['synced']]) }}
            @else {{ __('Waiting for the first sync.') }}
            @endif
        </p>
    @else
        <p v-pre class="text-gray-700 mb-3">{{ __('Finds the zone for :host, creates the porro_blocklist list if the account doesn’t have it yet, and adds one rule blocking it, next to your existing rules, never replacing them.', ['host' => $host ?: __('this site')]) }}</p>
        @if ($canManage)
            <form method="POST" action="{{ $connectUrl }}">
                @csrf
                <button type="submit" class="btn-primary" @disabled(! $steps['token'])>{{ __('Connect to Cloudflare') }}</button>
            </form>
        @endif
        <p class="text-xs text-gray-700 mt-2">{{ __('Or on the server:') }} <code>{{ $command }}</code></p>
        @if ($cloudflare['error'])
            <p v-pre class="text-xs text-red-500 mt-1">{{ $cloudflare['error'] }}</p>
        @endif
    @endif
</div>
