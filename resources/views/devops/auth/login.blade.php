<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso - ZIGO DevOps</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#0f172a;color:#0f172a;font-family:Arial,sans-serif}.login-shell{width:100%;max-width:440px}.brand{text-align:center;color:#fff;font-size:31px;font-weight:900;margin-bottom:18px}.card{background:#fff;border-radius:20px;padding:30px;box-shadow:0 24px 60px rgba(0,0,0,.3)}h1{font-size:24px;margin:0 0 8px}.subtitle{color:#64748b;line-height:1.45;margin:0 0 22px}.environment{display:inline-block;background:#e2e8f0;color:#334155;border-radius:999px;padding:7px 11px;font-size:12px;font-weight:900;margin-bottom:20px}label{display:block;font-weight:900;font-size:14px;margin:0 0 7px}.field{margin-bottom:17px}input[type="email"],input[type="password"]{width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:9px;font-size:15px}.remember{display:flex;align-items:center;gap:9px;font-weight:700;color:#475569;margin-bottom:20px}.remember input{width:17px;height:17px}.btn{width:100%;padding:13px;border:0;border-radius:10px;background:#2563eb;color:#fff;font-size:16px;font-weight:900;cursor:pointer}.notice{margin-top:20px;padding:13px;border-radius:10px;background:#fff7ed;color:#9a3412;font-size:13px;line-height:1.45}.errors{background:#fee2e2;color:#991b1b;border-radius:10px;padding:12px;margin-bottom:18px;font-weight:800}.errors ul{margin:0;padding-left:18px}
    </style>
</head>
<body>
<main class="login-shell">
    <div class="brand">ZIGO DevOps</div>
    <section class="card">
        <span class="environment">Ambiente: {{ strtoupper(app()->environment()) }}</span>
        <h1>Acceso al Centro de Despliegues</h1>
        <p class="subtitle">Inicia sesión con tus credenciales internas autorizadas.</p>
        @if($errors->any())<div class="errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ url('/login') }}">
            @csrf
            <div class="field"><label for="email">Correo electrónico</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></div>
            <div class="field"><label for="password">Contraseña</label><input id="password" type="password" name="password" autocomplete="current-password" required></div>
            <label class="remember"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Recordarme</label>
            <button class="btn" type="submit">Iniciar sesión</button>
        </form>
        <div class="notice"><strong>Acceso restringido.</strong> Este portal es exclusivo para personal autorizado de operación DevOps ZIGO.</div>
    </section>
</main>
</body>
</html>
