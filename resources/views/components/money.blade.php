@props(['cents', 'signed' => false])
@php
    $class = $signed ? ($cents < 0 ? 'text-bad' : ($cents > 0 ? 'text-good' : '')) : '';
    $text = \App\Support\Money::format($cents);
    if ($signed && $cents > 0) {
        $text = '+'.$text;
    }
@endphp
<span {{ $attributes->class(['tabular-nums whitespace-nowrap', $class]) }}>{{ $text }}</span>
