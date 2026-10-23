<div class="card p-0 h-full">
    <header class="flex justify-between items-center p-4 border-b">
        <h2 class="flex items-center">
            <span class="h-6 w-6 mr-2 text-gray-800">{!! $icon !!}</span>
            <a href="{{ $utilityUrl }}">{{ __('Porro Site Health') }}</a>
        </h2>
    </header>
    <p class="px-4 pt-3 text-sm text-gray-700">@include('porro::partials.summary')</p>
    @if (! empty($top))
        @include('porro::v5.issues', ['issues' => $top, 'compact' => true])
    @endif
    <p class="p-4 text-sm border-t">
        <a href="{{ $utilityUrl }}" class="text-blue hover:text-blue-800">{{ $more ? trans_choice('See :count more|See :count more', $more, ['count' => $more]) : __('Open Porro Site Health') }}</a>
        @unless ($connected)
            <span class="text-gray-700"> · {{ __('Connect Porro for uptime, SSL and domain checks') }}</span>
        @endunless
    </p>
</div>
