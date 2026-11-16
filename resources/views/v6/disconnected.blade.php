{{-- Shown once, after disconnecting: whether Porro was told. Tinted, not filled, so it reads in light and dark mode alike. --}}
<div role="status" class="rounded-xl" style="display: flex; flex-direction: column; gap: 4px; padding: 16px 20px; background: rgb(127 127 127 / .1)" v-pre>
    <p style="font-size: 15px; font-weight: 500; line-height: 1.5">{{ $disconnected['title'] }}</p>
    <p class="text-gray-600 dark:text-gray-400" style="font-size: 14px; line-height: 1.5">{{ $disconnected['body'] }}</p>
</div>
