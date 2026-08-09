@props(['label','value'])
<article {{ $attributes->class('z-card z-metric') }}><small>{{ $label }}</small><strong>{{ $value }}</strong>{{ $slot }}</article>
