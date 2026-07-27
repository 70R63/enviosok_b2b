<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Productos API Hub - CRM ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:280px 1fr;min-height:100vh}
        .sidebar{background:#111827;color:white;padding:30px}
        .logo{font-size:26px;font-weight:900;margin-bottom:30px}
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:14px 0;background:rgba(255,255,255,.10);padding:13px;border-radius:12px}
        .menu a.active{background:#4361ee}
        .logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}
        .content{padding:40px}
        .title{font-size:38px;font-weight:900;margin-bottom:8px}
        .subtitle{color:#64748b;margin-bottom:28px}
        .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:24px}
        .card,.panel{background:white;border-radius:18px;padding:22px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
        .label{color:#64748b;font-weight:800;font-size:14px}
        .value{font-size:30px;font-weight:900;margin-top:8px}
        .badge{display:inline-block;padding:6px 10px;border-radius:999px;font-weight:800;font-size:12px;background:#e2e8f0}
        .badge-green{background:#dcfce7;color:#166534}
        .badge-red{background:#fee2e2;color:#991b1b}
        .badge-blue{background:#dbeafe;color:#1e40af}
        .badge-orange{background:#ffedd5;color:#9a3412}
        .btn{display:inline-block;background:#4361ee;color:white;text-decoration:none;font-weight:800;padding:12px 16px;border-radius:9px;border:none;cursor:pointer}
        .btn-gray{background:#334155}
        table{width:100%;border-collapse:collapse;margin-top:16px}
        th,td{padding:14px 12px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:middle}
        th{background:#f8fafc;font-weight:900}
        input[type=number]{width:160px;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px}
        input[type=checkbox]{width:20px;height:20px}
        .success{background:#dcfce7;color:#166534;padding:12px;border-radius:10px;margin-bottom:16px;font-weight:800}
        .error{background:#fee2e2;color:#991b1b;padding:12px;border-radius:10px;margin-bottom:16px;font-weight:800}
        .muted{color:#64748b;font-size:13px}
        .actions{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px}
        .footer-actions{display:flex;justify-content:flex-end;margin-top:22px}
        @media(max-width:1100px){.grid{grid-template-columns:repeat(2,1fr)}.layout{grid-template-columns:230px 1fr}.content{padding:24px}}
    </style>
</head>
<body>
<div class="layout">
    @include('crm.partials.sidebar')

    <main class="content">
        <div class="title">Productos API Hub</div>
        <div class="subtitle">
            Servicios contratados por {{ $apiClient->name }}.
        </div>

        @if(session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="error">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="actions">
            <a class="btn btn-gray" href="{{ route('crm.api-hub.show', $apiClient) }}">
                ← Volver al cliente API
            </a>
            <a class="btn btn-gray" href="{{ route('crm.api-hub.index') }}">
                Volver al listado
            </a>
        </div>

        <div class="grid">
            <div class="card">
                <div class="label">Productos disponibles</div>
                <div class="value">{{ $products->count() }}</div>
            </div>
            <div class="card">
                <div class="label">Productos habilitados</div>
                <div class="value">{{ $enabledProductsCount }}</div>
            </div>
            <div class="card">
                <div class="label">Consumo mensual por producto</div>
                <div class="value">{{ number_format($productUsageTotal) }}</div>
            </div>
            <div class="card">
                <div class="label">Límite global del cliente</div>
                <div class="value">{{ number_format($apiClient->monthly_limit) }}</div>
            </div>
        </div>

        <div class="panel">
            <h2>Catálogo contratado</h2>
            <p class="muted">
                Un límite vacío utiliza únicamente el límite global del cliente.
                Deshabilitar un producto bloquea sus endpoints sin afectar las API Keys.
            </p>

            <form method="POST" action="{{ route('crm.api-hub.products.update', $apiClient) }}">
                @csrf

                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Descripción</th>
                            <th>Facturable</th>
                            <th>Consumo del mes</th>
                            <th>Límite específico</th>
                            <th>Habilitado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($products as $index => $product)
                            @php
                                $assignment = $assignments->get($product->id);
                                $usage = (int) ($usageByProduct[$product->id] ?? 0);
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $product->name }}</strong>
                                    <div>
                                        <span class="badge badge-blue">{{ $product->code }}</span>
                                    </div>
                                </td>
                                <td>
                                    {{ $product->description }}
                                </td>
                                <td>
                                    @if($product->billable)
                                        <span class="badge badge-green">Sí</span>
                                    @else
                                        <span class="badge">No</span>
                                    @endif
                                </td>
                                <td>{{ number_format($usage) }}</td>
                                <td>
                                    <input
                                        type="hidden"
                                        name="products[{{ $index }}][product_id]"
                                        value="{{ $product->id }}"
                                    >
                                    <input
                                        type="number"
                                        name="products[{{ $index }}][monthly_limit]"
                                        value="{{ old('products.' . $index . '.monthly_limit', $assignment?->monthly_limit) }}"
                                        min="1"
                                        max="10000000"
                                        placeholder="Global"
                                    >
                                </td>
                                <td>
                                    <input
                                        type="hidden"
                                        name="products[{{ $index }}][enabled]"
                                        value="0"
                                    >
                                    <input
                                        type="checkbox"
                                        name="products[{{ $index }}][enabled]"
                                        value="1"
                                        @checked((bool) old('products.' . $index . '.enabled', $assignment?->active ?? false))
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="footer-actions">
                    <button class="btn" type="submit">
                        Guardar productos contratados
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>
