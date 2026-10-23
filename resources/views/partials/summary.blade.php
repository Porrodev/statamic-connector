@php($attention = $counts['critical'] + $counts['warning'])
@if ($attention > 0)
    {{ trans_choice(':count thing needs attention|:count things need attention', $attention, ['count' => $attention]) }}
@elseif ($counts['notice'] > 0)
    {{ trans_choice('All clear, with :count notice|All clear, with :count notices', $counts['notice'], ['count' => $counts['notice']]) }}
@else
    {{ __('All clear') }}
@endif
@if ($checkedAgo)
    · {{ __('checked :ago', ['ago' => $checkedAgo]) }}
@endif
