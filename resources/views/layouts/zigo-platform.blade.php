<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ZIGO Platform')</title>
    <style>
        :root{--blue:#315cf5;--ink:#10213b;--muted:#60708a;--line:#dfe7f3;--soft:#f5f8ff;--ok:#147d4f}
        *{box-sizing:border-box}body{margin:0;font-family:Inter,Arial,sans-serif;color:var(--ink);background:var(--soft);line-height:1.5}
        a{color:inherit}.nav{background:#fff;border-bottom:1px solid var(--line)}.nav-inner{max-width:1080px;margin:auto;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;gap:20px}.brand{font-size:22px;font-weight:900;text-decoration:none;color:var(--blue)}.nav-links{display:flex;gap:18px;align-items:center}.nav-links a{text-decoration:none;font-weight:700}
        main{max-width:1080px;margin:auto;padding:48px 24px 80px}.hero{padding:72px 0;max-width:760px}.eyebrow{color:var(--blue);font-weight:900;letter-spacing:.08em;text-transform:uppercase}.hero h1{font-size:clamp(38px,7vw,68px);line-height:1.03;margin:12px 0 20px}.lead{font-size:21px;color:var(--muted);max-width:680px}.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:30px}
        .btn{display:inline-block;border:0;border-radius:12px;padding:13px 20px;font-weight:800;text-decoration:none;cursor:pointer;font-size:16px}.btn-primary{background:var(--blue);color:#fff}.btn-secondary{background:#fff;color:var(--blue);border:1px solid var(--line)}
        .card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:26px;box-shadow:0 12px 35px rgba(27,56,112,.07)}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:18px}.price{font-size:30px;font-weight:900}.muted{color:var(--muted)}
        .wizard{max-width:760px;margin:auto}.steps{display:flex;gap:8px;margin-bottom:22px}.step{height:6px;flex:1;background:#dbe3f2;border-radius:9px}.step.active{background:var(--blue)}h1{line-height:1.15}.field{margin:17px 0}.field label,.label{display:block;font-weight:800;margin-bottom:6px}.field input,.field select{width:100%;padding:12px;border:1px solid #cbd6e7;border-radius:10px;font:inherit;background:#fff}.choice{display:block;border:1px solid var(--line);border-radius:12px;padding:14px;margin:10px 0}.choice input{margin-right:8px}.errors{background:#fff0f0;color:#9c2020;padding:14px;border-radius:10px}.summary{display:grid;grid-template-columns:1fr 1fr;gap:12px}.summary div{padding:11px 0;border-bottom:1px solid var(--line)}.total{font-size:24px;font-weight:900;color:var(--blue)}
        @media(max-width:640px){.nav-inner,main{padding-left:16px;padding-right:16px}.nav-links{gap:10px;font-size:14px}.hero{padding:45px 0}.summary{grid-template-columns:1fr}.card{padding:20px}}
    </style>
</head>
<body>
<header class="nav"><div class="nav-inner"><a class="brand" href="{{ route('zigo-platform.landing') }}">ZIGO Platform</a><nav class="nav-links"><a href="{{ route('zigo-platform.pricing') }}">Precios</a><a class="btn btn-primary" href="{{ route('zigo-platform.start') }}">Comenzar</a></nav></div></header>
<main>@yield('content')</main>
</body>
</html>
