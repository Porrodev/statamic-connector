<ui-widget title="{{ __('Site health') }}" href="{{ $utilityUrl }}">
    <div class="px-4 py-3 space-y-3">
        <p class="text-sm text-gray-600 dark:text-gray-400">@include('porro::partials.summary')</p>
        @if (! empty($top))
            @include('porro::v6.issues', ['issues' => $top, 'compact' => true])
        @endif
        <p class="text-sm">
            <a href="{{ $utilityUrl }}" class="underline underline-offset-2">{{ $more ? trans_choice('See :count more|See :count more', $more, ['count' => $more]) : __('Open Site health') }}</a>
            @unless ($connected)
                <span class="text-gray-600 dark:text-gray-400"> · {{ __('Connect Porro for uptime, SSL and domain checks') }}</span>
            @endunless
        </p>
    </div>
</ui-widget>
