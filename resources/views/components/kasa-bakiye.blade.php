@props(['milli' => 0, 'currency'])
@use('App\Support\Amount')

<span {{ $attributes->class([
    'tabular-nums whitespace-nowrap',
    'text-stone-400' => $milli === 0,
    'font-medium text-stone-900' => $milli > 0,
    'font-medium text-red-600' => $milli < 0,
]) }}>{{ Amount::formatMilli($milli, $currency) }}</span>
