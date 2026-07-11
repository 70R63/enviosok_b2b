<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Negocios ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f4f7fb;color:#111827;min-height:100vh;display:flex;align-items:center;justify-content:center}
        .box{width:900px;max-width:95%;display:grid;grid-template-columns:1fr 1fr;background:white;border-radius:22px;box-shadow:0 20px 50px rgba(0,0,0,.15);overflow:hidden}
        .brand{background:#0f766e;color:white;padding:60px 45px;display:flex;flex-direction:column;justify-content:center}
        .brand h1{font-size:42px;margin:0 0 18px;font-weight:900}
        .brand p{font-size:18px;line-height:1.6}
        .form{padding:55px 45px}
        .form h2{font-size:34px;margin:0 0 25px;font-weight:900}
        label{font-weight:900;font-size:14px;display:block;margin:14px 0 6px}
        input{width:100%;height:46px;border:1px solid #cbd5e1;border-radius:12px;padding:0 14px;box-sizing:border-box}
        .btn{width:100%;margin-top:22px;background:#facc15;border:none;border-radius:12px;padding:15px;font-weight:900;font-size:16px;cursor:pointer}
        .error{background:#fee2e2;color:#991b1b;padding:13px;border-radius:12px;font-weight:800;margin-bottom:18px}
    </style>
</head>
<body>

<div class="box">
    <div class="brand">
        <h1>ZIGO Negocios</h1>
        <p>Portal para empresas que necesitan administrar envíos, usuarios, direcciones, saldo, facturación y reportes.</p>
        <p><strong>Acceso empresarial.</strong></p>
    </div>

    <div class="form">
        <h2>Iniciar sesión</h2>

        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('negocios.login.post') }}">
            @csrf

            <label>Correo electrónico</label>
            <input type="email" name="email" value="{{ old('email') }}" required>

            <label>Contraseña</label>
            <input type="password" name="password" required>

            <button class="btn" type="submit">Ingresar a Negocios</button>
        </form>
    </div>
</div>

</body>
</html>