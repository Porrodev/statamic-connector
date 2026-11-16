{{-- Connect to Porro: the live site not connected, a development copy, or connected from here. --}}
<h2 class="mb-2 font-bold">{{ $connect['state'] === 'connected' ? __('Connected to Porro') : __('Not connected to Porro') }}</h2>
<div class="card mb-6">
    <div style="display: flex; align-items: center; gap: 32px; flex-wrap: wrap">
        <div style="flex: 1 1 320px; min-width: 0; display: flex; flex-direction: column; gap: 6px">
            @if ($connect['state'] === 'connected')
                <p class="font-medium text-base" v-pre>{{ $connect['workspace'] ? __('Connected to :workspace', ['workspace' => $connect['workspace']]) : __('Connected to Porro') }}</p>
                <p class="text-sm text-gray-700">{{ $connect['approvedAgo'] ? __('Approved :ago.', ['ago' => $connect['approvedAgo']]) : '' }} {{ __('The first report has been sent, and Porro now checks this site every hour.') }}</p>
                <p class="text-xs text-gray-600">{{ __('The key was made on this site and is kept encrypted in its database.') }}</p>
            @elseif ($connect['state'] === 'development')
                <p class="font-medium text-base">{{ __('This is a development copy') }}</p>
                <p class="text-sm text-gray-700" v-pre>{{ __('Its environment is “:environment”, so it does not connect and sends no reports. Deploy, then connect from the live site’s control panel. The checks below still run here.', ['environment' => $connect['environment']]) }}</p>
                <p class="text-xs text-gray-600">{{ __('Is this the live site? Its APP_ENV should say production. You can also set a site key.') }}</p>
            @else
                <p class="font-medium text-base">{{ __('Have this site checked every hour') }}</p>
                <p class="text-sm text-gray-700">{{ __('The checks below ran on this site just now. Connect it to Porro for hourly advisory checks, uptime from outside, and an alert the moment something changes.') }}</p>
                <p class="text-xs text-gray-600" v-pre>{{ __('Opens :host to sign in and approve. No key to copy.', ['host' => $connect['porroHost']]) }}</p>
            @endif
        </div>
        @if ($connect['state'] === 'connected')
            <a href="{{ $connect['openUrl'] }}" target="_blank" rel="noopener" class="btn" style="flex-shrink: 0">{{ __('Open in Porro') }}</a>
        @elseif ($connect['state'] === 'ready')
            @include('porro::partials.connect-button')
        @endif
    </div>
</div>
