@props(['eyebrow'=>null,'title'])
<header {{ $attributes->class('z-page-header') }}><div>@if($eyebrow)<div class="z-eyebrow">{{ $eyebrow }}</div>@endif<h1>{{ $title }}</h1>@isset($description)<div class="z-muted">{{ $description }}</div>@endisset</div>@isset($actions)<div class="z-cluster">{{ $actions }}</div>@endisset</header>
