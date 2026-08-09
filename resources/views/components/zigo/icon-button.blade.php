@props(['label','type'=>'button'])
<button type="{{ $type }}" aria-label="{{ $label }}" {{ $attributes->class('z-icon-btn') }}>{{ $slot }}</button>
