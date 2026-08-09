@props(['label'=>'Acciones'])
<section {{ $attributes->class('z-sheet') }} aria-label="{{ $label }}">{{ $slot }}</section>
