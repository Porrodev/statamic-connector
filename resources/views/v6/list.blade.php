{{-- Statamic 6. Searches, reasons and who added an entry come from people, so they sit in v-pre. --}}
@php
    $title = $list === 'safelist' ? __('Safelist') : __('Blocklist');
    $query = fn (array $change) => $listUrl.'?'.http_build_query(array_filter(['q' => $search, 'filter' => $filter === 'all' ? null : $filter, ...$change]));
@endphp
<div class="max-w-page mx-auto space-y-6" v-cloak>
    <div>
        <a href="{{ $blockingUrl }}" class="text-sm underline underline-offset-2">← {{ __('Blocking') }}</a>
        <ui-header title="{{ $title }}" icon="{{ $icon }}"></ui-header>
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ trans_choice(':count entry|:count entries', $total, ['count' => number_format($total)]) }}@if ($list === 'safelist') · {{ __('Never blocked or flagged') }}@endif</p>
    </div>

    @include('porro::v6.flash')

    @if ($failed)
        <ui-card><p class="text-sm">{{ __('The blocklist tables aren’t there yet. Run php artisan migrate on this site.') }}</p></ui-card>
    @endif

    <ui-panel>
        <ui-card>
            <form method="GET" action="{{ $listUrl }}" class="flex flex-wrap gap-2 mb-3">
                @if ($filter !== 'all')<input type="hidden" name="filter" value="{{ $filter }}">@endif
                <div class="flex-1 min-w-48" v-pre><input type="text" name="q" value="{{ $search }}" placeholder="{{ __('Search: an IP, a range, a reason or who added it') }}" aria-label="{{ __('Search') }}" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-1.5 text-sm"></div>
                <ui-button type="submit" text="{{ __('Search') }}"></ui-button>
                @if ($search !== '')<ui-button variant="ghost" href="{{ $query(['q' => null]) }}" text="{{ __('Clear') }}"></ui-button>@endif
            </form>
            <div class="flex flex-wrap gap-2 mb-3">
                @foreach ($filters as $f)
                    @if ($f['count'] > 0 || $f['key'] === 'all' || $f['key'] === $filter)
                        <a href="{{ $query(['filter' => $f['key'] === 'all' ? null : $f['key']]) }}" @if ($f['key'] === $filter) aria-current="true" @endif>
                            <ui-badge size="sm" color="{{ $f['key'] === $filter ? 'blue' : 'default' }}" text="{{ $f['label'] }} {{ number_format($f['count']) }}"></ui-badge>
                        </a>
                    @endif
                @endforeach
            </div>
            @include('porro::v6.entries', ['empty' => $search !== '' || $filter !== 'all' ? __('Nothing matches.') : ($list === 'safelist' ? __('Nothing is safelisted.') : __('Nothing is blocked.'))])
            @if ($pages > 1)
                <div class="flex items-center justify-between mt-3 text-sm">
                    @if ($currentPage > 1)<ui-button size="sm" href="{{ $query(['page' => $currentPage - 1]) }}" text="← {{ __('Newer') }}"></ui-button>@else<span></span>@endif
                    <span class="text-xs text-gray-600 dark:text-gray-400">{{ __('Page :page of :pages · :count shown', ['page' => $currentPage, 'pages' => $pages, 'count' => number_format($shown)]) }}</span>
                    @if ($currentPage < $pages)<ui-button size="sm" href="{{ $query(['page' => $currentPage + 1]) }}" text="{{ __('Older') }} →"></ui-button>@else<span></span>@endif
                </div>
            @endif
        </ui-card>
    </ui-panel>
</div>
