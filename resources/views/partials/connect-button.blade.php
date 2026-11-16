{{-- The branded button: black, or near-white in the control panel's dark mode so it still stands out. The mark keeps its own colours on its black tile. --}}
{{-- The control panel is a Vue template, which drops a plain style tag, so the dark rule is a component. Where it doesn't apply the button stays black. --}}
<component is="style">.dark .porro-connect-button { background: #f2f2f0 !important; color: #0a0a0a !important; }</component>
<form method="POST" action="{{ $connect['url'] }}" style="flex-shrink: 0; margin: 0">
    @csrf
    <button type="submit" class="porro-connect-button" style="display: inline-flex; align-items: center; gap: 10px; padding: 9px 18px 9px 12px; border: 0; border-radius: 8px; background: #0a0a0a; color: #fff; font-size: 15px; font-weight: 500; line-height: normal; white-space: nowrap; cursor: pointer">
        <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 5.647px; background: #000" aria-hidden="true">
            <img src="{{ $connect['mark'] }}" alt="" width="14.1176" height="14.1176" style="display: block; width: 14.1176px; height: 14.1176px; max-width: none">
        </span>
        {{ __('Connect to Porro') }}
    </button>
</form>
