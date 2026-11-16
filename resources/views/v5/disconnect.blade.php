{{-- Disconnect from Porro: the quiet button, and the dialog to confirm, drawn like the block and safelist one. The button opens it (resources/js/dialog.js); Cancel closes it without sending anything, and the red button posts the form. --}}
<button type="button" class="btn-flat" data-porro-open="porro-disconnect">{{ __('Disconnect') }}</button>
<dialog id="porro-disconnect" aria-labelledby="porro-disconnect-title" class="card shadow-lg" style="width: min(32rem, calc(100vw - 2rem)); margin: auto; border: 0; padding: 0;">
    <form method="POST" action="{{ $connect['disconnectUrl'] }}" class="p-6">
        @csrf
        <h2 id="porro-disconnect-title" class="text-lg font-bold mb-4" style="overflow-wrap: anywhere" v-pre>{{ __('Disconnect :host from Porro?', ['host' => $connect['host'] ?: __('this site')]) }}</h2>
        @include('porro::partials.disconnect')
        <div class="flex justify-end mt-6">
            <button type="submit" class="btn mr-2" formmethod="dialog" formnovalidate>{{ __('Cancel') }}</button>
            <button type="submit" class="btn-danger">{{ __('Disconnect') }}</button>
        </div>
    </form>
</dialog>
