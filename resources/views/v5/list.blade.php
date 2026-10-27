{{-- Statamic 5. Searches, reasons and who added an entry come from people, so they sit in v-pre. --}}
@php
    $query = fn (array $change) => $listUrl.'?'.http_build_query(array_filter(['q' => $search, 'filter' => $filter === 'all' ? null : $filter, ...$change]));
@endphp
<header class="mb-6">
    <a href="{{ $blockingUrl }}" class="text-sm text-blue hover:text-blue-800">&larr; {{ __('Blocking') }}</a>
    <h1 class="mt-1">{{ $list === 'safelist' ? __('Safelist') : __('Blocklist') }}</h1>
    <p class="text-sm text-gray-700">{{ trans_choice(':count entry|:count entries', $total, ['count' => number_format($total)]) }}@if ($list === 'safelist') · {{ __('Never blocked or flagged') }}@endif</p>
</header>

@include('porro::v5.flash')

@if ($failed)
    <div class="card mb-6 text-sm">{{ __('The blocklist tables aren’t there yet. Run php artisan migrate on this site.') }}</div>
@endif

<div class="card p-0 mb-6 text-sm">
    <form method="GET" action="{{ $listUrl }}" class="flex flex-wrap p-4 pb-2">
        @if ($filter !== 'all')<input type="hidden" name="filter" value="{{ $filter }}">@endif
        <input v-pre type="text" name="q" value="{{ $search }}" placeholder="{{ __('Search: an IP, a range, a reason or who added it') }}" aria-label="{{ __('Search') }}" class="input-text flex-1 mr-2 mb-1">
        <button type="submit" class="btn mr-2 mb-1">{{ __('Search') }}</button>
        @if ($search !== '')<a href="{{ $query(['q' => null]) }}" class="btn mb-1">{{ __('Clear') }}</a>@endif
    </form>
    <div class="flex flex-wrap px-4 pb-3">
        @foreach ($filters as $f)
            @if ($f['count'] > 0 || $f['key'] === 'all' || $f['key'] === $filter)
                <a href="{{ $query(['filter' => $f['key'] === 'all' ? null : $f['key']]) }}" class="badge-pill-sm mr-1 mb-1 {{ $f['key'] === $filter ? 'bg-blue text-white' : '' }}" @if ($f['key'] === $filter) aria-current="true" @endif>{{ $f['label'] }} {{ number_format($f['count']) }}</a>
            @endif
        @endforeach
    </div>
    @include('porro::v5.entries', ['empty' => $search !== '' || $filter !== 'all' ? __('Nothing matches.') : ($list === 'safelist' ? __('Nothing is safelisted.') : __('Nothing is blocked.'))])
    @if ($pages > 1)
        <div class="flex items-center justify-between px-4 py-3 border-t">
            @if ($currentPage > 1)<a href="{{ $query(['page' => $currentPage - 1]) }}" class="btn btn-sm">&larr; {{ __('Newer') }}</a>@else<span></span>@endif
            <span class="text-xs text-gray-700">{{ __('Page :page of :pages · :count shown', ['page' => $currentPage, 'pages' => $pages, 'count' => number_format($shown)]) }}</span>
            @if ($currentPage < $pages)<a href="{{ $query(['page' => $currentPage + 1]) }}" class="btn btn-sm">{{ __('Older') }} &rarr;</a>@else<span></span>@endif
        </div>
    @endif
</div>
