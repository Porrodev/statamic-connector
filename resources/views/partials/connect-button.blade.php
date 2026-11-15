{{-- The branded button: Porro's own colours in light and dark mode alike. The mark is never recoloured. --}}
<form method="POST" action="{{ $connect['url'] }}" style="flex-shrink: 0; margin: 0">
    @csrf
    <button type="submit" style="display: inline-flex; align-items: center; gap: 10px; padding: 9px 18px 9px 12px; border: 0; border-radius: 8px; background: #0a0a0a; color: #fff; font-size: 15px; font-weight: 500; line-height: normal; white-space: nowrap; cursor: pointer">
        <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 5.647px; background: #000" aria-hidden="true">
            <img src="{{ $connect['mark'] }}" alt="" width="14.1176" height="14.1176" style="display: block; width: 14.1176px; height: 14.1176px; max-width: none">
        </span>
        {{ __('Connect to Porro') }}
    </button>
</form>
