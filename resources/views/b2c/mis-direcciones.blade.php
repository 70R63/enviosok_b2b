<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis direcciones - ZIGO</title>

    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
        .sidebar{background:#2563eb;color:white;padding:30px}
        .logo{margin-bottom:25px;}
        .zigo-logo{
            text-align:center;
            padding:8px;
        }

        .zigo-img{
            width:180px;
            max-width:100%;
            display:block;
            margin:0 auto;
            animation:zigoEntrance 1s ease-out;
            transition:all .35s ease;
        }

        .zigo-logo:hover .zigo-img{
            transform:scale(1.03);
            filter:
                drop-shadow(0 0 8px rgba(0,255,255,.45))
                drop-shadow(0 0 14px rgba(0,128,255,.35));
        }

        .zigo-tagline{
            margin-top:8px;
            font-size:10px;
            letter-spacing:2px;
            color:#dce7f7;
            text-transform:uppercase;
            font-weight:600;
            line-height:1.5;
        }

        @keyframes zigoEntrance{
            from{
                opacity:0;
                transform:translateX(-35px);
            }
            to{
                opacity:1;
                transform:translateX(0);
            }
        }
        .menu a,.logout-btn{display:block;color:white;text-decoration:none;font-weight:800;margin:18px 0;background:rgba(255,255,255,.12);padding:14px;border-radius:12px}
        .logout-btn{width:100%;border:none;text-align:left;cursor:pointer;font-size:16px}

        .content{padding:40px}
        .title{font-size:36px;font-weight:900;margin-bottom:8px}
        .subtitle{color:#64748b;margin-bottom:30px}

        .card{background:white;border-radius:18px;padding:20px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
        .toolbar{display:flex;justify-content:space-between;gap:16px;margin-bottom:18px}
        .search{width:420px;height:42px;border:1px solid #cbd5e1;border-radius:10px;padding:0 14px}

        table{width:100%;border-collapse:collapse}
        th,td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}
        th{background:#f8fafc;font-weight:900}

        .btn{display:inline-block;padding:10px 14px;border-radius:10px;text-decoration:none;font-weight:900;font-size:13px;border:none;cursor:pointer}
        .primary{background:#2563eb;color:white}
        .secondary{background:#6b7280;color:white}
        .success{background:#16a34a;color:white}
        .badge{background:#dbeafe;color:#1d4ed8;padding:5px 10px;border-radius:999px;font-weight:900;font-size:12px}
        .empty{background:#eff6ff;color:#1e40af;padding:16px;border-radius:12px;font-weight:700}
        .alert{background:#dcfce7;color:#166534;padding:14px;border-radius:12px;font-weight:800;margin-bottom:20px}

        .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);align-items:center;justify-content:center;z-index:999}
        .modal-content{background:white;width:760px;max-width:95%;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.25)}
        .modal-header{display:flex;justify-content:space-between;align-items:center;padding:18px 22px;border-bottom:1px solid #e5e7eb}
        .modal-header h2{margin:0}
        .modal-close{border:none;background:none;font-size:26px;font-weight:900;cursor:pointer;color:#6b7280}
        .modal-body{padding:22px}
        .modal-footer{display:flex;justify-content:flex-end;gap:12px;padding:18px 22px;border-top:1px solid #e5e7eb}

        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .full{grid-column:1 / -1}
        label{display:block;font-weight:800;font-size:13px;margin-bottom:6px;color:#334155}
        input,select{width:100%;height:42px;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;box-sizing:border-box}
        .suggestions{background:white;border:1px solid #e5e7eb;border-radius:10px;position:absolute;z-index:1000;width:100%;box-shadow:0 10px 24px rgba(0,0,0,.12)}
        .suggestion-item{padding:12px;cursor:pointer}
        .suggestion-item:hover{background:#eff6ff}
        .autocomplete-wrap{position:relative}

        .dropdown{
            position:relative;
        }

        .btn-action{
            background:#fff;
            border:1px solid #f97316;
            color:#f97316;
            padding:8px 14px;
            border-radius:10px;
            cursor:pointer;
            font-weight:600;
        }

        .dropdown-menu{
            display:none;
            position:absolute;
            right:0;
            top:40px;
            min-width:220px;
            background:#fff;
            border-radius:12px;
            box-shadow:0 10px 25px rgba(0,0,0,.15);
            z-index:1000;
        }

        .dropdown-menu.show{
            display:block;
        }

        .dropdown-menu a{
            display:block;
            padding:12px 16px;
            color:#111827;
            text-decoration:none;
            border-bottom:1px solid #f3f4f6;
        }

        .dropdown-menu a:hover{
            background:#f9fafb;
        }

        .dropdown-menu .danger{
            color:#dc2626;
        }

        .dropdown-danger{
            width:100%;
            background:white;
            border:none;
            text-align:left;
            padding:12px 16px;
            color:#dc2626;
            cursor:pointer;
            font-weight:700;
        }
    </style>
</head>
<body>

<div class="layout">
    <aside class="sidebar">
        <div class="logo zigo-logo">
            <img src="{{ asset('img/zigo-logo.png') }}" alt="ZIGO" class="zigo-img">

            <div class="zigo-tagline">
                Tecnología • Logística • Conexión
            </div>
        </div>

        <div class="menu">
            <a href="{{ route('b2c.dashboard') }}">Inicio</a>
            <a href="{{ route('b2c.nuevo-envio') }}">Nuevo envío</a>
            <a href="{{ route('b2c.mis-envios') }}">Mis envíos</a>
            <a href="{{ route('b2c.incidencias') }}">Incidencias</a>
            <a href="{{ route('b2c.mis-pagos') }}">Mis pagos</a>
            <a href="{{ route('b2c.mis-direcciones') }}">Mis direcciones</a>
            <a href="{{ route('b2c.prepago') }}">Prepago</a>
            <a href="#">Adeudos</a>
            <a href="{{ route('b2c.configuracion') }}">Configuración</a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="logout-btn" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </aside>

    <main class="content">
        <div class="title">Mis direcciones</div>
        <div class="subtitle">Administra tus direcciones frecuentes.</div>

        @if(session('success'))
            <div class="alert">{{ session('success') }}</div>
        @endif

        <div class="card">
            <div class="toolbar">
                <input class="search" id="searchInput" placeholder="Buscar por nombre, dirección, código postal...">

                <button type="button" class="btn primary" onclick="openModal()">
                    Nueva dirección
                </button>
            </div>

            @if($direcciones->isEmpty())
                <div class="empty">Aún no tienes direcciones guardadas.</div>
            @else
                <table id="direccionesTable">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Dirección</th>
                            <th>Código postal</th>
                            <th>Ciudad</th>
                            <th>Fuente</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($direcciones as $direccion)
                            <tr>
                                <td>
                                    @if($direccion->favorita)
                                        ⭐
                                    @endif

                                    {{ $direccion->alias ?: $direccion->nombre }}

                                    <div style="color:#64748b;font-size:12px;margin-top:4px">
                                        {{ $direccion->nombre }}
                                    </div>
                                </td>
                                <td><span class="badge">{{ ucfirst(strtolower($direccion->tipo)) }}</span></td>
                                <td>
                                    {{ $direccion->calle }} #{{ $direccion->num_ext }}
                                    {{ $direccion->num_int ? 'Int. '.$direccion->num_int : '' }}
                                    Col. {{ $direccion->colonia }}
                                </td>
                                <td>{{ $direccion->cp }}</td>
                                <td>{{ $direccion->ciudad }} - {{ $direccion->estado }}</td>
                                <td>Libreta</td>
                            </tr>
                            <td>
                                <div class="dropdown">
                                    <button class="btn-action" onclick="toggleMenu({{ $direccion->id }})">
                                        Acciones ▼
                                    </button>

                                    <div id="menu-{{ $direccion->id }}" class="dropdown-menu">

                                        @if($direccion->tipo == 'ORIGEN')
                                            <a href="{{ route('b2c.nuevo-envio') }}?origen={{ $direccion->id }}">
                                                🚚 Nuevo envío
                                            </a>
                                        @else
                                            <a href="{{ route('b2c.nuevo-envio') }}?destino={{ $direccion->id }}">
                                                📦 Enviar a esta dirección
                                            </a>
                                        @endif

                                        <a href="#">
                                            ✏️ Editar dirección
                                        </a>

                                        <form method="POST" action="{{ route('b2c.mis-direcciones.eliminar', $direccion->id) }}">
                                            @csrf
                                            <button type="submit" class="dropdown-danger" onclick="return confirm('¿Eliminar esta dirección?')">
                                                🗑️ Eliminar dirección
                                            </button>
                                        </form>

                                    </div>
                                </div>
                            </td>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </main>
</div>

<div id="addressModal" class="modal">
    <div class="modal-content">
        <form method="POST" action="{{ route('b2c.mis-direcciones.guardar') }}">
            @csrf

            <div class="modal-header">
                <h2>Nueva dirección</h2>
                <button type="button" class="modal-close" onclick="closeModal()">×</button>
            </div>

            <div class="modal-body">
                <div class="form-grid">
                    <div>
                        <label>Nombre</label>
                        <input name="nombre" required>
                    </div>

                    <div>
                        <label>Empresa</label>
                        <input name="empresa">
                    </div>

                    <div>
                        <label>Correo</label>
                        <input type="email" name="email">
                    </div>

                    <div>
                        <label>Teléfono</label>
                        <input name="telefono" required>
                    </div>

                    <div>
                        <label>Calle</label>
                        <input name="calle" required>
                    </div>

                    <div>
                        <label>No. exterior</label>
                        <input name="num_ext" required>
                    </div>

                    <div>
                        <label>No. interior</label>
                        <input name="num_int">
                    </div>

                    <div>
                        <label>Referencias / Entre calles</label>
                        <input name="referencias">
                    </div>

                    <div>
                        <label>Código postal</label>
                        <input type="text" id="direccion_cp" name="cp" maxlength="5" required>
                    </div>

                    <div>
                        <label>&nbsp;</label>
                        <button type="button" class="btn primary" onclick="buscarColoniasDireccion()">
                            Buscar
                        </button>
                    </div>

                    <div class="full">
                        <label>Colonia</label>
                        <select id="direccion_colonia" name="colonia" required onchange="seleccionarColoniaDireccion()">
                            <option value="">-- Selecciona una colonia --</option>
                        </select>
                    </div>

                    <div>
                        <label>Municipio</label>
                        <input id="direccion_ciudad" name="ciudad" readonly required>
                    </div>

                    <div>
                        <label>Estado</label>
                        <input id="direccion_estado" name="estado" readonly required>
                    </div>

                    <div>
                        <label>Tipo</label>
                        <select name="tipo" required>
                            <option value="ORIGEN">Origen</option>
                            <option value="DESTINO">Destino</option>
                        </select>
                    </div>

                    <div>
                        <label>Alias</label>
                        <input name="alias" placeholder="Casa, oficina, bodega">
                    </div>

                    <div class="full" style="margin-top:10px">
                        <label style="display:flex;align-items:center;gap:8px">
                            <input type="checkbox" name="favorita" value="1" style="width:auto;height:auto">
                            Marcar como favorita
                        </label>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn secondary" onclick="closeModal()">Cerrar</button>
                <button type="submit" class="btn primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('addressModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('addressModal').style.display = 'none';
}

document.getElementById('searchInput')?.addEventListener('input', function () {
    const value = this.value.toLowerCase();
    document.querySelectorAll('#direccionesTable tbody tr').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(value) ? '' : 'none';
    });
});
</script>

<script>
let coloniasDireccion = [];

function buscarColoniasDireccion() {
    const cp = document.getElementById('direccion_cp').value.trim();
    const coloniaSelect = document.getElementById('direccion_colonia');
    const ciudad = document.getElementById('direccion_ciudad');
    const estado = document.getElementById('direccion_estado');

    coloniaSelect.innerHTML = '<option value="">Buscando...</option>';
    ciudad.value = '';
    estado.value = '';

    if (cp.length !== 5) {
        coloniaSelect.innerHTML = '<option value="">Código postal inválido</option>';
        return;
    }

    fetch(`/b2c/cp/colonias?cp=${encodeURIComponent(cp)}`)
        .then(response => response.json())
        .then(response => {
            coloniasDireccion = response.data || [];

            coloniaSelect.innerHTML = '<option value="">-- Selecciona una colonia --</option>';

            coloniasDireccion.forEach((item, index) => {
                const option = document.createElement('option');
                option.value = item.d_asenta;
                option.dataset.index = index;
                option.textContent = item.d_asenta;
                coloniaSelect.appendChild(option);
            });
        })
        .catch(() => {
            coloniaSelect.innerHTML = '<option value="">Error consultando colonias</option>';
        });
}

function seleccionarColoniaDireccion() {
    const select = document.getElementById('direccion_colonia');
    const index = select.options[select.selectedIndex]?.dataset.index;

    if (index === undefined) return;

    const item = coloniasDireccion[index];

    document.getElementById('direccion_ciudad').value = item.d_mnpio || '';
    document.getElementById('direccion_estado').value = item.d_estado || item.codigo_estado || '';
}
</script>
<script>
function toggleMenu(id)
{
    document
        .querySelectorAll('.dropdown-menu')
        .forEach(menu => {
            if(menu.id !== 'menu-' + id){
                menu.classList.remove('show');
            }
        });

    document
        .getElementById('menu-' + id)
        .classList
        .toggle('show');
}

document.addEventListener('click', function(e)
{
    if(!e.target.closest('.dropdown'))
    {
        document
            .querySelectorAll('.dropdown-menu')
            .forEach(menu => menu.classList.remove('show'));
    }
});
</script>
</body>
</html>