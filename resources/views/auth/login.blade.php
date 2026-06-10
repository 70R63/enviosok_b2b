<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar sesión - ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827}
        .container{max-width:980px;margin:60px auto;padding:0 20px}
        .card{display:grid;grid-template-columns:1fr 1fr;background:white;border-radius:22px;overflow:hidden;box-shadow:0 16px 40px rgba(0,0,0,.10)}
        .side{background:linear-gradient(135deg,#3b82f6,#4f46e5);color:white;padding:50px}
        .side h1{font-size:42px;margin:0 0 20px}
        .side p{font-size:18px;line-height:1.5}
        .form{padding:50px}
        .form h2{font-size:32px;margin:0 0 25px}
        .group{margin-bottom:18px}
        label{display:block;font-weight:800;margin-bottom:8px}
        input{width:100%;box-sizing:border-box;padding:15px;border:1px solid #d1d5db;border-radius:12px;font-size:16px}
        .error{background:#fee2e2;color:#991b1b;padding:12px;border-radius:10px;margin-bottom:18px;font-weight:700}
        .btn{width:100%;background:#facc15;color:#111827;border:none;border-radius:12px;padding:16px;font-size:18px;font-weight:900;cursor:pointer}
        .link{margin-top:18px;text-align:center}
        .link a{color:#2563eb;font-weight:800;text-decoration:none}
        @media(max-width:768px){.card{grid-template-columns:1fr}.side{padding:30px}.form{padding:30px}}
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="side">
            <h1>Bienvenido</h1>
            <p>Inicia sesión para consultar tus envíos y agilizar futuras cotizaciones.</p>

            <div style="margin-top:55px;text-align:center;">
                <img src="{{ asset('img/zigo-logo.png') }}" alt="ZIGO" style="width:260px;max-width:90%;height:auto;">
                <div style="margin-top:12px;font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:900;color:#eaf2ff;">
                    Tecnología • Logística • Conexión
                </div>
            </div>
        </div>

        <div class="form">
            <h2>Iniciar sesión</h2>

            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="group">
                    <label>Correo electrónico</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus>
                </div>

                <div class="group">
                    <label>Contraseña</label>
                    <input type="password" name="password" required>
                </div>

                <button type="submit" class="btn">Ingresar</button>
            </form>

            <div class="link">
                ¿No tienes cuenta? <a href="{{ route('b2c.register') }}">Regístrate</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>