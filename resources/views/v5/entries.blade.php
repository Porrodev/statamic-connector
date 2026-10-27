@if (empty($entries))
    <p class="p-4 text-sm">{{ $empty }}</p>
@else
    @foreach ($entries as $entry)
        <div class="flex flex-wrap items-center px-4 py-2 text-sm {{ $loop->first ? '' : 'border-t' }}">
            <span v-pre class="font-mono text-xs mr-4" style="min-width: 12rem">{{ $entry['cidr'] }}</span>
            <span v-pre class="flex-1 mr-4" style="overflow-wrap: anywhere">{{ $entry['note'] }}</span>
            <span v-pre class="text-gray-700 text-xs mr-4">{{ $entry['source'] }}@if ($entry['by']) · {{ $entry['by'] }}@endif</span>
            <span class="text-gray-700 text-xs mr-4" style="min-width: 6rem">{{ $entry['expires'] }}</span>
            @if ($canManage && ! $entry['fromConfig'])
                <form method="POST" action="{{ $remove }}">
                    @csrf
                    <input type="hidden" name="ip" value="{{ $entry['cidr'] }}">
                    <button type="submit" class="text-red-500 hover:text-red-800 text-xs">{{ __('Remove') }}</button>
                </form>
            @endif
        </div>
    @endforeach
@endif
