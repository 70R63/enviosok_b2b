@props(['tone'=>'info'])
<span {{ $attributes->class('z-badge'.($tone!=='info'?' z-badge--'.$tone:'')) }}>{{ $slot }}</span>
