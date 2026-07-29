<aside class="sidebar">
    <div class="logo">CRM ZIGO</div>

    @php
        $newProspectsCount = \App\Models\CrmClient::where('commercial_status', 'prospecto')
            ->where(function ($query) {
                $query->whereNull('reviewed_at')
                    ->orWhereIn('lead_status', ['nuevo', 'sin_revisar']);
            })
            ->count();

        $pendingIdentityCount =
            \Illuminate\Support\Facades\Schema::hasTable(
                'b2c_identity_verifications'
            )
                ? \App\Models\B2cIdentityVerification::whereIn(
                    'status',
                    [
                        'PENDIENTE',
                        'EN_REVISION',
                    ]
                )->count()
                : 0;
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

        <a href="{{ route('crm.identity.index') }}"
           class="{{ request()->routeIs('crm.identity.*') ? 'active' : '' }}">
            Verificaciones

            @if($pendingIdentityCount > 0)
                <span style="float:right;background:#f59e0b;color:#111827;border-radius:999px;padding:2px 8px;font-size:12px;">
                    {{ $pendingIdentityCount }}
                </span>
            @endif
        </a>

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

        <a href="{{ route('crm.guias.index') }}"
           class="{{ request()->routeIs('crm.guias.*') ? 'active' : '' }}">
            Guías
        </a>

        <a href="#">Incidencias</a>

        <a href="{{ route('crm.adeudos.index') }}"
           class="{{ request()->routeIs('crm.adeudos.*') ? 'active' : '' }}">
            Adeudos

            @php
                $pendingDebtCount = \Illuminate\Support\Facades\Schema::hasTable('b2c_adeudos')
                    ? \App\Models\B2cAdeudo::whereIn('estatus', ['PENDIENTE', 'PAGO_INICIADO'])->count()
                    : 0;
            @endphp

            @if($pendingDebtCount > 0)
                <span style="float:right;background:#ef4444;color:white;border-radius:999px;padding:2px 8px;font-size:12px;">
                    {{ $pendingDebtCount }}
                </span>
            @endif
        </a>

        <a href="#">Pagos</a>

        <a href="{{ route('crm.facturacion.index') }}"
           class="{{ request()->routeIs('crm.facturacion.*') ? 'active' : '' }}">
            Facturación B2C

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
        <a href="{{ route('crm.shipping.index') }}"
           class="{{ request()->routeIs('crm.shipping.*') ? 'active' : '' }}">
            Paqueterías
        </a>

        <a href="{{ route('crm.api-hub.index') }}"
           class="{{ request()->routeIs('crm.api-hub.*') && !request()->routeIs('crm.api-hub.billing.*') ? 'active' : '' }}">
            Clientes API
        </a>

        <a href="{{ route('crm.api-hub.billing.index') }}"
           class="{{ request()->routeIs('crm.api-hub.billing.*') ? 'active' : '' }}">
            Facturación de integraciones

            @php
                $pendingApiBillingCount = \Illuminate\Support\Facades\Schema::hasTable('api_billing_requests')
                    ? \App\Models\ApiBillingRequest::whereIn('status', ['SOLICITADA', 'EN_PROCESO'])->count()
                    : 0;
            @endphp

            @if($pendingApiBillingCount > 0)
                <span style="float:right;background:#f59e0b;color:#111827;border-radius:999px;padding:2px 8px;font-size:12px;">
                    {{ $pendingApiBillingCount }}
                </span>
            @endif
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