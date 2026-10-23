<ul class="divide-y divide-gray-200 dark:divide-gray-800">
    @foreach ($issues as $issue)
        <li class="py-3 first:pt-0 last:pb-0 flex flex-col gap-1 {{ ($compact ?? false) ? '' : 'sm:flex-row sm:items-start sm:gap-3' }}">
            <div class="shrink-0" @unless ($compact ?? false) style="min-width: 8.5rem" @endunless>
                <ui-badge size="sm" color="{{ $issue['style']['color'] }}" text="{{ __($issue['style']['label']) }}"></ui-badge>
            </div>
            {{-- v-pre: the control panel compiles this HTML as a Vue template, and titles come from outside. --}}
            <div v-pre class="min-w-0 flex-1 text-sm">
                <p class="font-medium break-words">
                    @if ($issue['url'])
                        <a href="{{ $issue['url'] }}" target="_blank" rel="noopener" class="underline decoration-gray-300 underline-offset-2 hover:decoration-current">{{ $issue['title'] }}</a>
                    @else
                        {{ $issue['title'] }}
                    @endif
                    @if ($issue['scope'] === 'server')
                        <span class="text-gray-500"> · {{ __('server') }}</span>
                    @endif
                    @if ($issue['acknowledged'])
                        <span class="text-gray-500"> · {{ __('acknowledged') }}</span>
                    @endif
                </p>
                @if ($issue['detail'])
                    <p class="text-gray-600 dark:text-gray-400 break-words">{{ $issue['detail'] }}</p>
                @endif
                @if ($issue['fix'])
                    <p class="mt-1"><code class="text-xs break-all">{{ $issue['fix'] }}</code></p>
                @endif
            </div>
        </li>
    @endforeach
</ul>
