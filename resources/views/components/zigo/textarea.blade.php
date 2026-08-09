@props(['name'])
<textarea name="{{ $name }}" {{ $attributes->class('z-textarea') }}>{{ $slot }}</textarea>
