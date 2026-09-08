@props(['href' => null, 'variant' => 'secondary', 'type' => 'button'])

@php($classes = 'button button--'.$variant)

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
