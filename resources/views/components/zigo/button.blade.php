@props(['variant'=>'primary','type'=>'button','block'=>false])
@php($classes='z-btn z-btn--'.$variant.($block?' z-btn--block':''))
@if($attributes->has('href'))<a {{ $attributes->class($classes) }}>{{ $slot }}</a>@else<button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>@endif
