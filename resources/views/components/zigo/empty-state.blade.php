@props(['title','icon'=>'○'])
<div {{ $attributes->class('z-empty') }}><div class="z-empty__icon" aria-hidden="true">{{ $icon }}</div><h2>{{ $title }}</h2><div class="z-muted">{{ $slot }}</div>@isset($action)<div class="z-form-actions">{{ $action }}</div>@endisset</div>
