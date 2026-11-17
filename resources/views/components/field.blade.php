@props(['label', 'for' => null])

<div {{ $attributes }}>
    <label class="label" @if ($for) for="{{ $for }}" @endif>{{ $label }}</label>
    {{ $slot }}
</div>
