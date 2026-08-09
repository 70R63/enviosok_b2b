@props(['name','accept'=>null,'help'=>null])
<div class="z-field"><input class="z-file" type="file" name="{{ $name }}" @if($accept) accept="{{ $accept }}" @endif {{ $attributes }}>@if($help)<span class="z-help">{{ $help }}</span>@endif</div>
