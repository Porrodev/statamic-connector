{{-- Disconnect from Porro: the quiet button, and Statamic's own modal to confirm. The modal opens itself from its trigger, laid out as the control panel's confirmation modal is, and the red button posts the form. --}}
<ui-modal title="{{ __('Disconnect :host from Porro?', ['host' => $connect['host'] ?: __('this site')]) }}">
    <template #trigger>
        <ui-button variant="ghost" text="{{ __('Disconnect') }}"></ui-button>
    </template>
    @include('porro::partials.disconnect')
    <template #footer>
        <form method="POST" action="{{ $connect['disconnectUrl'] }}" class="flex items-center justify-end space-x-3 pt-3 pb-1" style="margin: 0">
            @csrf
            <ui-modal-close>
                <ui-button type="button" variant="ghost" text="{{ __('Cancel') }}"></ui-button>
            </ui-modal-close>
            <ui-button type="submit" variant="danger" text="{{ __('Disconnect') }}"></ui-button>
        </form>
    </template>
</ui-modal>
