{{-- Statamic 6: rendered by the control panel's Vue app, so ui-* components work here. --}}
<div class="max-w-page mx-auto space-y-6" v-cloak>
    <ui-header title="{{ __('Porro Site Health') }}" icon="{{ $icon }}">
        <template #actions>
            <form method="POST" action="{{ $refreshUrl }}">
                @csrf
                <ui-button type="submit" text="{{ __('Check again') }}"></ui-button>
            </form>
        </template>
    </ui-header>

    @include('porro::v6.flash')

    <p class="text-sm text-gray-600 dark:text-gray-400" role="status">
        @include('porro::partials.summary')
    </p>

    @if ($failed)
        <ui-card>
            <p class="text-sm">{{ __('The checks couldn’t run just now. Try again in a minute; the error is in the Laravel log.') }}</p>
        </ui-card>
    @endif

    @if ($connected)
        <ui-panel heading="{{ __('From Porro') }}" subheading="{{ __('Uptime, SSL, the domain and the server, checked from outside every few minutes.') }}">
            <ui-card>
                @if (! $porroOk)
                    <p class="text-sm">{{ $porro['error'] ?? __('Couldn’t reach Porro just now.') }} {{ __('Showing the checks run on this site instead.') }}</p>
                @elseif (empty($found))
                    <p class="text-sm">{{ __('Porro has nothing open for this site.') }}</p>
                @else
                    @include('porro::v6.issues', ['issues' => $found])
                @endif
                @if ($porroUrl)
                    <div class="mt-4">
                        <ui-button href="{{ $porroUrl }}" target="_blank" variant="ghost" size="sm" icon-append="external-link" text="{{ __('Open this site in Porro') }}"></ui-button>
                    </div>
                @endif
            </ui-card>
        </ui-panel>
    @endif

    <ui-panel heading="{{ __('Checked on this site') }}">
        <ui-card>
            @if (empty($local))
                <p class="text-sm">{{ __('Nothing needs attention.') }}</p>
            @else
                @include('porro::v6.issues', ['issues' => $local])
            @endif
            @include('porro::partials.checked')
        </ui-card>
    </ui-panel>

    @includeWhen($activity, 'porro::v6.activity')

    @unless ($connected)
        <ui-panel heading="{{ __('Watch this site from outside too') }}">
            <ui-card>
                @include('porro::partials.upsell')
            </ui-card>
        </ui-panel>
    @endunless
</div>
