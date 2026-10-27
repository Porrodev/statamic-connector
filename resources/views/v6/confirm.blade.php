{{-- The block and safelist confirmation, opened by the Laravel connector's Confirm script. It fills in the text, and shows the red or the plain confirm button. --}}
<dialog id="porro-confirm" aria-labelledby="porro-confirm-title" class="rounded-xl bg-white text-gray-800 ring ring-gray-200 shadow-ui-md dark:bg-gray-850 dark:text-gray-200 dark:ring-gray-700/80" style="width: min(28rem, calc(100vw - 2rem)); margin: auto; border: 0; padding: 0; box-shadow: 0 20px 50px rgb(0 0 0 / .25);">
    <form method="dialog" class="p-6">
        <h2 id="porro-confirm-title" class="text-lg font-semibold text-gray-900 dark:text-white" style="overflow-wrap: anywhere" data-porro-title></h2>
        <p class="mt-2 text-sm" data-porro-body></p>
        <div class="mt-6 flex justify-end gap-2">
            <ui-button type="submit" value="cancel" data-porro-cancel text="{{ __('Cancel') }}"></ui-button>
            <ui-button type="submit" variant="danger" value="ok" data-porro-ok="danger" text="{{ __('Block') }}"></ui-button>
            <ui-button type="submit" variant="primary" value="ok" data-porro-ok="safe" style="display: none" text="{{ __('Safelist') }}"></ui-button>
        </div>
    </form>
</dialog>
