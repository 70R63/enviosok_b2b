@props(['interactive'=>false])
<section {{ $attributes->class('z-card'.($interactive?' z-card--interactive':'')) }}>{{ $slot }}</section>
