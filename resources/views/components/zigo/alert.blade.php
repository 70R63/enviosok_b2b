@props(['tone'=>'info','title'=>null])
<div role="{{ $tone==='danger'?'alert':'status' }}" {{ $attributes->class('z-alert'.($tone!=='info'?' z-alert--'.$tone:'')) }}>@if($title)<strong>{{ $title }}</strong>@endif {{ $slot }}</div>
