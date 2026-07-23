<aside class="sidebar">
    <div class="logo">CRM ZIGO</div>

    @php
        $newProspectsCount = \App\Models\CrmClient::where('commercial_status', 'prospecto')
            ->where(function ($query) {
                $query->whereNull('reviewed_at')
                    ->orWhereIn('lead_status', ['nuevo', 'sin_revisar']);
            })
            ->count();
    @endphp

    <div class="menu">
        <a href="{{ route('crm.dashboard') }}"
           class="{{ request()->routeIs('crm.dashboard') ? 'active' : '' }}">
            Dashboard
        </a>

        <a href="{{ route('crm.clientes.index') }}"
           class="{{ request()->routeIs('crm.clientes.*') && !request('commercial_status') ? 'active' : '' }}">
            Clientes
        </a>

        <a href="#">Usuarios B2C</a>
        <a href="#">Usuarios Negocios</a>
        <a href="#">Usuarios Soporte</a>
        <a href="#">Empresas</a>

        <a href="{{ route('crm.clientes.index', ['commercial_status' => 'prospecto']) }}"
        class="{{ request()->routeIs('crm.clientes.*') && request('commercial_status') === 'prospecto' ? 'active' : '' }}">
            Prospectos

            @if($newProspectsCount > 0)
                <span style="float:right;background:#ef4444;color:white;border-radius:999px;padding:2px 8px;font-size:12px;">
                    {{ $newProspectsCount }}
                </span>
            @endif
        </a>

        <a href="#">Guías</a>
        <a href="#">Incidencias</a>
        <a href="#">Adeudos</a>
        <a href="#">Pagos</a>

        <a href="{{ route('crm.facturacion.index') }}"
           class="{{ request()->routeIs('crm.facturacion.*') ? 'active' : '' }}">
            Facturación

            @php
                $pendingInvoiceCount = \Illuminate\Support\Facades\Schema::hasTable('b2c_invoice_requests')
                    ? \App\Models\B2cInvoiceRequest::whereIn('status', ['SOLICITADA', 'EN_PROCESO'])->count()
                    : 0;
            @endphp

            @if($pendingInvoiceCount > 0)
                <span style="float:right;background:#f59e0b;color:#111827;border-radius:999px;padding:2px 8px;font-size:12px;">
                    {{ $pendingInvoiceCount }}
                </span>
            @endif
        </a>
        <a href="#">Paqueterías</a>

        <a href="{{ route('crm.api-hub.index') }}"
           class="{{ request()->routeIs('crm.api-hub.*') ? 'active' : '' }}">
            API Hub
        </a>

        <a href="{{ route('crm.pricing.index') }}"
            class="{{ request()->routeIs('crm.pricing.*') ? 'active' : '' }}">
            Tarifas
        </a>

        <a href="{{ route('crm.seguridad.index') }}"
           class="{{ request()->routeIs('crm.seguridad.*') ? 'active' : '' }}">
            Seguridad
        </a>

        <a href="#">Configuración</a>

        <form method="POST" action="{{ route('crm.logout') }}">
            @csrf
            <button class="logout-btn" type="submit">Cerrar sesión</button>
        </form>
    </div>
</aside>