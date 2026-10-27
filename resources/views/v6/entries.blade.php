@if (empty($entries))
    <p class="text-sm">{{ $empty }}</p>
@else
    <ul class="divide-y divide-gray-200 dark:divide-gray-800 text-sm">
        @foreach ($entries as $entry)
            <li class="py-2 flex flex-wrap items-center gap-x-4 gap-y-1">
                <span v-pre class="font-mono text-xs" style="min-width: 12rem">{{ $entry['cidr'] }}</span>
                <span v-pre class="flex-1 min-w-0 break-words">{{ $entry['note'] }}</span>
                <span v-pre class="text-gray-600 dark:text-gray-400 text-xs">{{ $entry['source'] }}@if ($entry['by']) · {{ $entry['by'] }}@endif</span>
                <span class="text-gray-600 dark:text-gray-400 text-xs" style="min-width: 6rem">{{ $entry['expires'] }}</span>
                @if ($canManage && ! $entry['fromConfig'])
                    <form method="POST" action="{{ $remove }}">
                        @csrf
                        <input type="hidden" name="ip" value="{{ $entry['cidr'] }}">
                        <ui-button type="submit" size="sm" variant="ghost" text="{{ __('Remove') }}"></ui-button>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>
@endif
