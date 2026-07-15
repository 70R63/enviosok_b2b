<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Próximamente | ZIGO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(67, 97, 238, .18), transparent 34%),
                linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            color: #111827;
            min-height: 100vh;
        }

        .page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1.08fr .92fr;
            align-items: center;
            gap: 48px;
            max-width: 1180px;
            margin: 0 auto;
            padding: 48px 24px;
        }

        .coming-logo-wrap {
            text-align: left;
            margin-bottom: 28px;
        }

        .coming-logo {
            width: 340px;
            max-width: 90%;
            height: auto;
            display: block;
            filter: drop-shadow(0 12px 30px rgba(72, 106, 255, .22));
            transition: transform .25s ease, filter .25s ease;
            will-change: transform;
        }

        .coming-logo-wrap:hover .coming-logo {
            transform: translateX(8px) scale(1.04);
            filter: drop-shadow(0 18px 34px rgba(72, 106, 255, .36));
        }

        .coming-kicker {
            font-size: 14px;
            font-weight: 900;
            letter-spacing: 6px;
            color: #3f51e8;
            margin-bottom: 12px;
            text-transform: uppercase;
        }

        h1 {
            font-size: 64px;
            line-height: 1;
            margin: 0 0 18px;
            color: #111827;
            font-weight: 900;
        }

        h1 strong {
            color: #f26a00;
        }

        .lead {
            font-size: 19px;
            line-height: 1.55;
            color: #475569;
            max-width: 680px;
            margin: 0 0 26px;
        }

        .tags {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 28px;
        }

        .tags span {
            background: rgba(255,255,255,.85);
            border: 1px solid #dbeafe;
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 900;
            color: #334155;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
        }

        .countdown {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            max-width: 620px;
        }

        .count-box {
            background: white;
            border-radius: 18px;
            padding: 18px 12px;
            text-align: center;
            box-shadow: 0 16px 36px rgba(15, 23, 42, .08);
            border: 1px solid #e5e7eb;
        }

        .count-box strong {
            display: block;
            font-size: 34px;
            color: #4361ee;
            font-weight: 900;
        }

        .count-box span {
            display: block;
            margin-top: 4px;
            color: #64748b;
            font-weight: 800;
            font-size: 13px;
        }

        .form-card {
            background: white;
            border-radius: 28px;
            padding: 34px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .14);
            border: 1px solid #e5e7eb;
        }

        .form-card h2 {
            margin: 0 0 10px;
            font-size: 32px;
            font-weight: 900;
        }

        .form-card p {
            margin: 0 0 22px;
            color: #64748b;
            line-height: 1.5;
        }

        label {
            display: block;
            font-weight: 900;
            margin-bottom: 7px;
            color: #111827;
        }

        input, select, textarea {
            width: 100%;
            height: 46px;
            border-radius: 13px;
            border: 1px solid #cbd5e1;
            padding: 0 14px;
            font-size: 15px;
            margin-bottom: 15px;
            outline: none;
            background: #fff;
        }

        textarea {
            min-height: 96px;
            padding-top: 12px;
            resize: vertical;
        }

        input:focus, select:focus, textarea:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, .12);
        }

        .btn {
            width: 100%;
            height: 52px;
            border: none;
            border-radius: 15px;
            background: #f26a00;
            color: white;
            font-weight: 900;
            font-size: 17px;
            cursor: pointer;
            box-shadow: 0 14px 28px rgba(242, 106, 0, .26);
        }

        .success {
            background: #dcfce7;
            border: 1px solid #86efac;
            color: #166534;
            border-radius: 14px;
            padding: 14px 16px;
            font-weight: 900;
            margin-bottom: 16px;
        }

        .errors {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 16px;
            font-weight: 800;
        }

        .footer-note {
            margin-top: 18px;
            color: #64748b;
            font-size: 13px;
            text-align: center;
        }

        @media(max-width: 900px) {
            .page {
                grid-template-columns: 1fr;
                padding: 32px 18px;
            }

            .coming-logo-wrap {
                text-align: center;
            }

            .coming-logo {
                margin: 0 auto;
                width: 300px;
            }

            .coming-kicker {
                text-align: center;
                letter-spacing: 4px;
            }

            h1 {
                font-size: 44px;
                text-align: center;
            }

            .lead {
                font-size: 17px;
                text-align: center;
            }

            .tags {
                justify-content: center;
            }

            .countdown {
                grid-template-columns: repeat(2, 1fr);
            }

            .form-card {
                padding: 24px;
            }
        }
    </style>
</head>
<body>

<main class="page">
    <section>
        <div class="coming-logo-wrap">
            <img src="{{ asset('img/zigo-logo.png') }}" alt="ZIGO" class="coming-logo">
        </div>

        <div class="coming-kicker">Tecnología • Logística • Conexión</div>

        <h1>
            Próximamente
        </h1>

        <p class="lead">
            Cotiza con distintas paqueterías, genera tus guías y administra tus envíos desde un solo lugar.
            Regístrate para tener acceso antes del lanzamiento.
        </p>

        <div class="tags">
            <span>Compara opciones</span>
            <span>Genera guías en línea</span>
            <span>Rastrea tus envíos</span>
        </div>

        <div class="countdown" id="countdown">
            <div class="count-box">
                <strong id="days">20</strong>
                <span>Días</span>
            </div>
            <div class="count-box">
                <strong id="hours">00</strong>
                <span>Horas</span>
            </div>
            <div class="count-box">
                <strong id="minutes">00</strong>
                <span>Min</span>
            </div>
            <div class="count-box">
                <strong id="seconds">00</strong>
                <span>Seg</span>
            </div>
        </div>
    </section>

    <section class="form-card">
        <h2>Acceso anticipado</h2>
        <p>
            Déjanos tus datos y te avisaremos antes del lanzamiento.
            Nuestro equipo podrá contactarte por teléfono o WhatsApp para darte seguimiento.
        </p>

        @if(session('success'))
            <div class="success">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="errors">
                Revisa los campos obligatorios antes de continuar.
            </div>
        @endif

        <form method="POST" action="{{ route('waitlist.store') }}">
            @csrf

            <label>Nombre completo</label>
            <input type="text" name="name" value="{{ old('name') }}" required>

            <label>Correo electrónico</label>
            <input type="email" name="email" value="{{ old('email') }}" required>

            <label>Teléfono / WhatsApp</label>
            <input type="text" name="phone" value="{{ old('phone') }}" required>

            <label>Tipo de usuario</label>
            <select name="segment" required>
                <option value="">Selecciona una opción</option>
                <option value="persona" {{ old('segment') === 'persona' ? 'selected' : '' }}>Persona</option>
                <option value="emprendedor" {{ old('segment') === 'emprendedor' ? 'selected' : '' }}>Emprendedor</option>
                <option value="ecommerce" {{ old('segment') === 'ecommerce' ? 'selected' : '' }}>Ecommerce</option>
                <option value="empresa" {{ old('segment') === 'empresa' ? 'selected' : '' }}>Empresa</option>
                <option value="api" {{ old('segment') === 'api' ? 'selected' : '' }}>API / Integrador</option>
            </select>

            <label>Mensaje opcional</label>
            <textarea name="message" placeholder="Cuéntanos qué tipo de envíos realizas o qué necesitas.">{{ old('message') }}</textarea>

            <button type="submit" class="btn">
                Quiero acceso anticipado
            </button>
        </form>

        <div class="footer-note">
            Tus datos se usarán únicamente para dar seguimiento al lanzamiento de ZIGO.
        </div>
    </section>
</main>

<script>
    const launchDate = new Date();
    launchDate.setDate(launchDate.getDate() + 20);
    launchDate.setHours(0, 0, 0, 0);

    function updateCountdown() {
        const now = new Date().getTime();
        const distance = launchDate.getTime() - now;

        if (distance <= 0) {
            document.getElementById('days').textContent = '00';
            document.getElementById('hours').textContent = '00';
            document.getElementById('minutes').textContent = '00';
            document.getElementById('seconds').textContent = '00';
            return;
        }

        document.getElementById('days').textContent = Math.floor(distance / (1000 * 60 * 60 * 24));
        document.getElementById('hours').textContent = String(Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))).padStart(2, '0');
        document.getElementById('minutes').textContent = String(Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60))).padStart(2, '0');
        document.getElementById('seconds').textContent = String(Math.floor((distance % (1000 * 60)) / 1000)).padStart(2, '0');
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);
</script>

</body>
</html>