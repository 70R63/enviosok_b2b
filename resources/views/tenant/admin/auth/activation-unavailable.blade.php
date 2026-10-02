<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Activación no disponible · ZIGO</title>
    <link rel="stylesheet" href="{{ asset('css/zigo-design-system.css') }}">
    <style>body{min-height:100vh;display:grid;place-items:center;background:#0b2445;padding:1rem}.panel{width:min(480px,100%);background:#fff;padding:2rem;border-radius:18px}.panel a{overflow-wrap:anywhere}</style>
</head>
<body>
<main class="panel">
    <p>ZIGO</p>
    <h1>Este enlace de activación ya no está disponible.</h1>
    <p>Por seguridad, los enlaces de activación tienen una vigencia de {{ $validity }} y sólo pueden utilizarse una vez.</p>
    <p>Tu plataforma continúa disponible.</p>
    <p>URL administrativa: <a href="{{ $adminUrl }}">{{ $adminUrl }}</a></p>
    <p>Solicita un nuevo enlace de activación.</p>
    <a class="btn btn-primary" href="{{ $adminUrl }}">IR A MI PLATAFORMA</a>
</main>
</body>
</html>
