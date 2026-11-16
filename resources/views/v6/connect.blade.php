{{-- Connect to Porro: the live site not connected, a development copy, connected from here, or a key Porro turned away. --}}
@php($title = 'font-size: 16px; font-weight: 500; line-height: 1.5')
@php($body = 'font-size: 14px; line-height: 1.5')
@php($hint = 'font-size: 13px; line-height: 1.5')
<ui-panel heading="{{ $connect['state'] === 'connected' ? __('Connected to Porro') : ($connect['turnedAway'] ? __('No longer connected to Porro') : __('Not connected to Porro')) }}">
    <ui-card>
        <div style="display: flex; align-items: center; gap: 32px; flex-wrap: wrap">
            <div style="flex: 1 1 320px; min-width: 0; display: flex; flex-direction: column; gap: 6px">
                @if ($connect['state'] === 'connected')
                    <p style="{{ $title }}" v-pre>{{ $connect['workspace'] ? __('Connected to :workspace', ['workspace' => $connect['workspace']]) : __('Connected to Porro') }}</p>
                    <p class="text-gray-600 dark:text-gray-400" style="{{ $body }}">{{ $connect['approvedAgo'] ? __('Approved :ago.', ['ago' => $connect['approvedAgo']]) : '' }} {{ __('The first report has been sent, and Porro now checks this site every hour.') }}</p>
                    <p class="text-gray-500 dark:text-gray-500" style="{{ $hint }}">{{ __('The key was made on this site and is kept encrypted in its database.') }}</p>
                @elseif ($connect['turnedAway'])
                    <p style="{{ $title }}">{{ __('Porro turned this site’s key away') }}</p>
                    <p class="text-gray-600 dark:text-gray-400" style="{{ $body }}" v-pre>{{ __('The site was removed from :workspace, or given a new key there.', ['workspace' => $connect['turnedAway']['workspace'] ?: 'Porro']) }} {{ $connect['turnedAway']['date'] ? __('Reports stopped on :date at :time.', ['date' => $connect['turnedAway']['date'], 'time' => $connect['turnedAway']['time']]) : '' }} {{ __('The old key has been deleted from this site.') }}</p>
                    <p class="text-gray-500 dark:text-gray-500" style="{{ $hint }}" v-pre>{{ __('Connect again to carry on. Opens :host to sign in and approve.', ['host' => $connect['porroHost']]) }}</p>
                @elseif ($connect['state'] === 'development')
                    <p style="{{ $title }}">{{ __('This is a development copy') }}</p>
                    <p class="text-gray-600 dark:text-gray-400" style="{{ $body }}" v-pre>{{ __('Its environment is “:environment”, so it does not connect and sends no reports. Deploy, then connect from the live site’s control panel. The checks below still run here.', ['environment' => $connect['environment']]) }}</p>
                    <p class="text-gray-500 dark:text-gray-500" style="{{ $hint }}">{{ __('Is this the live site? Its APP_ENV should say production. You can also set a site key.') }}</p>
                @else
                    <p style="{{ $title }}">{{ __('Have this site checked every hour') }}</p>
                    <p class="text-gray-600 dark:text-gray-400" style="{{ $body }}">{{ __('The checks below ran on this site just now. Connect it to Porro for hourly advisory checks, uptime from outside, and an alert the moment something changes.') }}</p>
                    <p class="text-gray-500 dark:text-gray-500" style="{{ $hint }}" v-pre>{{ __('Opens :host to sign in and approve. No key to copy.', ['host' => $connect['porroHost']]) }}</p>
                @endif
            </div>
            @if ($connect['state'] === 'connected')
                <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0">
                    @includeWhen($connect['canDisconnect'], 'porro::v6.disconnect')
                    <ui-button href="{{ $connect['openUrl'] }}" target="_blank" text="{{ __('Open in Porro') }}"></ui-button>
                </div>
            @elseif ($connect['button'])
                @include('porro::partials.connect-button')
            @endif
        </div>
    </ui-card>
</ui-panel>
