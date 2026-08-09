@props(['label','name'=>null,'help'=>null,'error'=>null,'required'=>false])
<label class="z-field"><span>{{ $label }}@if($required) <span aria-hidden="true">*</span>@endif</span>{{ $slot }}@if($help)<span class="z-help">{{ $help }}</span>@endif @if($error)<span class="z-field-error" role="alert">{{ $error }}</span>@endif</label>
