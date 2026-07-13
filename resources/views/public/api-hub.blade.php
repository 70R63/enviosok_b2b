<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>API Hub ZIGO</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a;}
        .page{max-width:1100px;margin:0 auto;padding:80px 24px;}
        .back{display:inline-block;margin-bottom:30px;color:#4361ee;font-weight:800;text-decoration:none;}
        .hero{background:#fff;border-radius:28px;padding:50px;box-shadow:0 20px 50px rgba(15,23,42,.08);}
        h1{font-size:46px;margin:0 0 18px;}
        p{font-size:20px;line-height:1.5;color:#475569;}
        .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;margin-top:34px;}
        .card{background:#fff;border:1px solid #e5e7eb;border-radius:22px;padding:24px;box-shadow:0 12px 30px rgba(15,23,42,.05);}
        .card h3{margin:0 0 10px;color:#4361ee;}
        .cta{display:inline-block;margin-top:30px;background:#f26b00;color:#fff;padding:15px 26px;border-radius:14px;font-weight:900;text-decoration:none;}
        @media(max-width:800px){.grid{grid-template-columns:1fr;}h1{font-size:34px}.hero{padding:30px;}}
    </style>
</head>
<body>
<div class="page">
    <a class="back" href="{{ url('/') }}">← Volver a ZIGO</a>

    <div class="hero">
        <h1>API Hub ZIGO</h1>
        <p>
            Integra servicios logísticos en tus sistemas para consultar códigos postales,
            colonias, cobertura de paquetería y, en siguientes etapas, cotizaciones y generación de guías.
        </p>

        <div class="grid">
            <div class="card">
                <h3>Consulta de CP</h3>
                <p>Valida códigos postales, colonias, municipio, ciudad y estado desde tus aplicaciones.</p>
            </div>

            <div class="card">
                <h3>Cobertura logística</h3>
                <p>Identifica si existe cobertura disponible para operar envíos en una zona determinada.</p>
            </div>

            <div class="card">
                <h3>Control por cliente</h3>
                <p>Administra llaves API, planes, límites de uso y consumo desde el CRM de ZIGO.</p>
            </div>
        </div>

        <a class="cta" href="{{ route('landing.empresas') }}">Solicitar acceso empresarial</a>
    </div>
</div>
</body>
</html>