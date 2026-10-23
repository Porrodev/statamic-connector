@if (! empty($checked) || ! empty($unavailable))
    <div v-pre class="mt-4 text-xs text-gray-600 dark:text-gray-400 space-y-1">
        @if (! empty($checked))
            <p>{{ __('Checked') }}: {{ implode(' · ', $checked) }}.</p>
        @endif
        @foreach ($unavailable as $note)
            <p>{{ $note }}.</p>
        @endforeach
        <p>{{ __('Only package names leave the site, to Packagist’s advisories API. Support dates come from endoflife.date.') }}</p>
    </div>
@endif
