{{-- What "Disconnect" does, inside each control panel's own dialog. Inline styles, so it reads the same in both and in dark mode. --}}
@php($workspace = $connect['workspace'] ?: 'Porro')
@php($term = 'font-size: 13px; font-weight: 500; line-height: 1.5; margin: 0')
@php($detail = 'font-size: 13px; line-height: 1.5; margin: 2px 0 0; opacity: .75')
<div style="display: flex; flex-direction: column; gap: 14px" v-pre>
    <p style="font-size: 14px; line-height: 1.5; margin: 0">{{ __('This site stops sending reports to :workspace, and its key is deleted from this site.', ['workspace' => $workspace]) }}</p>
    <dl style="display: flex; flex-direction: column; gap: 12px; margin: 0; padding: 14px 16px; border-radius: 8px; background: rgba(127,127,127,.12)">
        <div>
            <dt style="{{ $term }}">{{ __('Stops') }}</dt>
            <dd style="{{ $detail }}">{{ __('Hourly reports of what is installed here, and signing in to this site from Porro.') }}</dd>
        </div>
        <div>
            <dt style="{{ $term }}">{{ __('Carries on') }}</dt>
            <dd style="{{ $detail }}">{{ __('The checks on this page. They run on this site and need no account.') }}</dd>
        </div>
        <div>
            <dt style="{{ $term }}">{{ __('In Porro') }}</dt>
            <dd style="{{ $detail }}">{{ __(':workspace keeps the site and its history. Uptime, SSL and domain checks go on from outside until someone removes the site there.', ['workspace' => $workspace]) }}</dd>
        </div>
    </dl>
    <p style="font-size: 13px; line-height: 1.5; margin: 0; opacity: .65">{{ __('You can connect again at any time.') }}</p>
</div>
