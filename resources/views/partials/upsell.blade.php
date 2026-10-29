<div class="text-sm space-y-3">
    <p>{{ __('These checks run inside the site. Porro watches it from outside as well, alongside every other site you look after:') }}</p>
    <ul class="list-disc pl-5 space-y-1" style="list-style: disc; padding-left: 1.25rem">
        <li>{{ __('Uptime from several regions, and alerts by email or Slack when it goes down') }}</li>
        <li>{{ __('SSL certificate and domain expiry, well before they lapse') }}</li>
        <li>{{ __('The server’s operating system and security updates') }}</li>
        <li>{{ __('Advisories, updates and support dates across all your sites in one place') }}</li>
    </ul>
    <p>{{ __('Add the site in Porro, then connect it with its site key:') }}</p>
    <pre class="text-xs p-2 rounded overflow-x-auto" style="background: rgba(127,127,127,.12)"><code>{{ $commands['connect'] }}</code></pre>
    <p><a href="https://porro.dev/?utm_source=statamic-connector&amp;utm_medium=control-panel&amp;utm_campaign=connectors" target="_blank" rel="noopener" class="underline">{{ __('Find out more at porro.dev') }}</a></p>
</div>
