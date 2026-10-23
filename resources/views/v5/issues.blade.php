<ul>
    @foreach ($issues as $issue)
        <li class="flex items-start p-4 {{ $loop->first ? '' : 'border-t' }}">
            <span class="shrink-0 mr-3 mt-1 inline-block rounded-full" style="width: .6rem; height: .6rem; background: {{ $issue['style']['hex'] }}" title="{{ __($issue['style']['label']) }}"></span>
            {{-- v-pre: the control panel compiles this HTML as a Vue template, and titles come from outside. --}}
            <div v-pre class="flex-1 min-w-0 text-sm">
                <p class="font-bold" style="overflow-wrap: anywhere">
                    <span class="sr-only">{{ __($issue['style']['label']) }}:</span>
                    @if ($issue['url'])
                        <a href="{{ $issue['url'] }}" target="_blank" rel="noopener" class="text-blue hover:text-blue-800">{{ $issue['title'] }}</a>
                    @else
                        {{ $issue['title'] }}
                    @endif
                    @if ($issue['scope'] === 'server')
                        <span class="text-gray-600 font-normal">· {{ __('server') }}</span>
                    @endif
                    @if ($issue['acknowledged'])
                        <span class="text-gray-600 font-normal">· {{ __('acknowledged') }}</span>
                    @endif
                </p>
                @if ($issue['detail'])
                    <p class="text-gray-700" style="overflow-wrap: anywhere">{{ $issue['detail'] }}</p>
                @endif
                @if ($issue['fix'])
                    <p class="mt-1"><code class="text-xs" style="overflow-wrap: anywhere">{{ $issue['fix'] }}</code></p>
                @endif
            </div>
            @unless ($compact ?? false)
                <span class="shrink-0 ml-3 text-2xs uppercase text-gray-600 hidden md:inline">{{ __($issue['style']['label']) }}</span>
            @endunless
        </li>
    @endforeach
</ul>
