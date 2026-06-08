<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro B2C - EnvíosOK</title>
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
            <h1>Crea tu cuenta</h1>
            <p>
                Guarda tus datos, consulta tus envíos y agiliza futuras cotizaciones.
            </p>
        </div>

        <div class="form">
            <h2>Registro</h2>

            @if ($errors->any())
                <div class="error">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('b2c.register.store') }}">
                @csrf

                <div class="group">
                    <label>Nombre</label>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </div>

                <div class="group">
                    <label>Apellido</label>
                    <input type="text" name="apellido_paterno" value="{{ old('apellido_paterno') }}" required>
                </div>

                <div class="group">
                    <label>Correo electrónico</label>
                    <input type="email" name="email" value="{{ old('email') }}" required>
                </div>

                <div class="group">
                    <label>Contraseña</label>
                    <input type="password" name="password" required>
                </div>

                <div class="group">
                    <label>Confirmar contraseña</label>
                    <input type="password" name="password_confirmation" required>
                </div>

                <button type="submit" class="btn">Crear cuenta</button>
            </form>

            <div class="link">
                ¿Ya tienes cuenta? <a href="{{ url('/login') }}">Inicia sesión</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>