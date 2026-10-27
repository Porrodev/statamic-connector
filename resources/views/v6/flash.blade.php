{{-- Tinted, not filled, so it reads in light and dark mode alike. --}}
@if (! empty($flash['error']))
    <div role="alert" class="rounded-lg border px-4 py-3 text-sm" style="border-color: rgb(220 38 38 / .45); background: rgb(220 38 38 / .08); color: rgb(220 38 38)" v-pre>{{ $flash['error'] }}</div>
@elseif (! empty($flash['success']))
    <div role="status" class="rounded-lg border px-4 py-3 text-sm" style="border-color: rgb(22 163 74 / .45); background: rgb(22 163 74 / .08); color: rgb(22 163 74)" v-pre>{{ $flash['success'] }}</div>
@endif
