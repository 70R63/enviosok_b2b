@if ($paginator->hasPages())
    @once
        <style>
            .customer-pagination{display:flex;justify-content:center;margin-top:1.5rem;overflow-x:auto;padding:2px 0 4px;-webkit-overflow-scrolling:touch}
            .customer-pagination__items{display:flex;align-items:center;gap:.35rem;min-width:max-content;margin:0;padding:0;list-style:none}
            .customer-pagination__link,.customer-pagination__current,.customer-pagination__disabled,.customer-pagination__ellipsis{min-height:36px;display:inline-flex;align-items:center;justify-content:center;padding:.45rem .7rem;border:1px solid var(--z-border);border-radius:9px;background:#fff;color:var(--z-text);font-size:.875rem;font-weight:750;line-height:1;text-decoration:none}
            .customer-pagination__link:hover{border-color:var(--z-primary);color:var(--z-primary)}
            .customer-pagination__link:focus-visible{outline:3px solid color-mix(in srgb,var(--z-primary) 25%,transparent);outline-offset:2px}
            .customer-pagination__current{border-color:var(--z-primary);background:var(--z-primary);color:#fff}
            .customer-pagination__disabled{background:var(--z-surface-subtle);color:var(--z-muted);cursor:not-allowed;opacity:.7}
            .customer-pagination__ellipsis{border-color:transparent;background:transparent;padding-inline:.35rem}
            @media(max-width:480px){.customer-pagination{justify-content:flex-start}.customer-pagination__link,.customer-pagination__current,.customer-pagination__disabled{min-height:34px;padding:.4rem .58rem;font-size:.82rem}}
        </style>
    @endonce

    <nav class="customer-pagination" aria-label="Paginación">
        <ul class="customer-pagination__items">
            @if ($paginator->onFirstPage())
                <li><span class="customer-pagination__disabled" aria-disabled="true">Anterior</span></li>
            @else
                <li><a class="customer-pagination__link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="customer-pagination__ellipsis" aria-hidden="true">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <li><span class="customer-pagination__current" aria-current="page">{{ $page }}</span></li>
                        @else
                            <li><a class="customer-pagination__link" href="{{ $url }}" aria-label="Ir a la página {{ $page }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li><a class="customer-pagination__link" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente</a></li>
            @else
                <li><span class="customer-pagination__disabled" aria-disabled="true">Siguiente</span></li>
            @endif
        </ul>
    </nav>
@endif
