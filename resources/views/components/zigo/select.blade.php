@props(['name'])
<select name="{{ $name }}" {{ $attributes->class('z-select') }}>{{ $slot }}</select>
