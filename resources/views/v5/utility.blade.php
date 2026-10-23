{{-- Statamic 5: the classic control panel's own classes. --}}
<header class="mb-6">
    @include('statamic::partials.breadcrumb', ['url' => cp_route('utilities.index'), 'title' => __('Utilities')])
    <div class="flex items-center justify-between">
        <h1 class="flex items-center"><span class="h-6 w-6 mr-2 text-gray-800">{!! $icon !!}</span>{{ __('Porro Site Health') }}</h1>
        <form method="POST" action="{{ $refreshUrl }}">
            @csrf
            <button type="submit" class="btn">{{ __('Check again') }}</button>
        </form>
    </div>
    <p class="text-sm text-gray-700 mt-2" role="status">@include('porro::partials.summary')</p>
</header>

@if ($failed)
    <div class="card mb-6">
        <p class="text-sm">{{ __('The checks couldn’t run just now. Try again in a minute; the error is in the Laravel log.') }}</p>
    </div>
@endif

@if ($connected)
    <h2 class="mb-2 font-bold">{{ __('From Porro') }}</h2>
    <p class="text-sm text-gray-700 mb-2">{{ __('Uptime, SSL, the domain and the server, checked from outside every few minutes.') }}</p>
    <div class="card p-0 mb-6">
        @if (! $porroOk)
            <p class="p-4 text-sm">{{ $porro['error'] ?? __('Couldn’t reach Porro just now.') }} {{ __('Showing the checks run on this site instead.') }}</p>
        @elseif (empty($found))
            <p class="p-4 text-sm">{{ __('Porro has nothing open for this site.') }}</p>
        @else
            @include('porro::v5.issues', ['issues' => $found])
        @endif
        @if ($porroUrl)
            <div class="p-4 border-t text-sm">
                <a href="{{ $porroUrl }}" target="_blank" rel="noopener" class="text-blue hover:text-blue-800">{{ __('Open this site in Porro') }} &rarr;</a>
            </div>
        @endif
    </div>
@endif

<h2 class="mb-2 font-bold">{{ __('Checked on this site') }}</h2>
<div class="card p-0 mb-6">
    @if (empty($local))
        <p class="p-4 text-sm">{{ __('Nothing needs attention.') }}</p>
    @else
        @include('porro::v5.issues', ['issues' => $local])
    @endif
    <div class="px-4 pb-4">@include('porro::partials.checked')</div>
</div>

@unless ($connected)
    <h2 class="mb-2 font-bold">{{ __('Watch this site from outside too') }}</h2>
    <div class="card mb-6">
        @include('porro::partials.upsell')
    </div>
@endunless
