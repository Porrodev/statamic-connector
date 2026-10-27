{{-- The block and safelist confirmation, opened by the Laravel connector's Confirm script. It fills in the text, and shows the red or the plain confirm button. --}}
<dialog id="porro-confirm" aria-labelledby="porro-confirm-title" class="card shadow-lg" style="width: min(28rem, calc(100vw - 2rem)); margin: auto; border: 0; padding: 0;">
    <form method="dialog" class="p-6">
        <h2 id="porro-confirm-title" class="text-lg font-bold mb-2" style="overflow-wrap: anywhere" data-porro-title></h2>
        <p class="text-sm" data-porro-body></p>
        <div class="flex justify-end mt-6">
            <button class="btn mr-2" value="cancel" data-porro-cancel>{{ __('Cancel') }}</button>
            <button class="btn-danger" value="ok" data-porro-ok="danger">{{ __('Block') }}</button>
            <button class="btn-primary" value="ok" data-porro-ok="safe" style="display: none">{{ __('Safelist') }}</button>
        </div>
    </form>
</dialog>
