<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ZIGO | Tu envío')</title>
    <link rel="stylesheet" href="{{ asset('css/b2c-responsive.css') }}">
    <style>
        :root{--blue:#3867f5;--dark:#0f172a;--muted:#64748b;--soft:#f5f8ff;--green:#15803d}
        *{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;color:var(--dark);background:linear-gradient(145deg,#fff 0%,var(--soft) 100%);min-height:100vh}
        .public-header{background:#fff;border-bottom:1px solid #e8edf7}.public-nav{max-width:1080px;margin:auto;padding:14px 24px;display:flex;align-items:center;justify-content:space-between;gap:20px}.public-logo{display:block;width:210px;max-width:55vw;height:auto}.public-help{color:var(--blue);font-weight:800;text-decoration:none}
        .public-main{max-width:920px;margin:0 auto;padding:54px 24px 72px}.public-card{background:#fff;border:1px solid #e8edf7;border-radius:24px;padding:34px;box-shadow:0 20px 55px rgba(30,64,175,.10)}
        @media(max-width:640px){.public-nav{padding:12px 18px}.public-main{padding:28px 16px 48px}.public-card{padding:24px 18px;border-radius:18px}.public-help{font-size:14px}}
    </style>
    @stack('styles')
</head>
<body>
<header class="public-header"><nav class="public-nav" aria-label="Navegación principal"><a href="{{ config('zigo_domains.portals.b2c.url') ?: config('app.url') }}"><img class="public-logo" src="{{ asset('img/zigo-logo.png') }}" alt="ZIGO"></a><a class="public-help" href="{{ rtrim((string) (config('zigo_domains.portals.b2c.url') ?: config('app.url')), '/') }}/soporte">Ayuda</a></nav></header>
<main class="public-main">@yield('content')</main>
</body>
</html>
