<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Soporte ZIGO</title>
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
        .cta{display:inline-block;margin-top:30px;background:#25d366;color:#fff;padding:15px 26px;border-radius:14px;font-weight:900;text-decoration:none;}
        @media(max-width:800px){.grid{grid-template-columns:1fr;}h1{font-size:34px}.hero{padding:30px;}}
    </style>
</head>
<body>
<div class="page">
    <a class="back" href="{{ url('/') }}">← Volver a ZIGO</a>

    <div class="hero">
        <h1>Soporte ZIGO</h1>
        <p>
            Te apoyamos con dudas sobre cotizaciones, pagos, generación de guías, rastreo,
            incidencias y operación de tus envíos.
        </p>

        <div class="grid">
            <div class="card">
                <h3>Cotizaciones y pagos</h3>
                <p>Ayuda para revisar costos, pagos aprobados, saldos y flujo de compra.</p>
            </div>

            <div class="card">
                <h3>Guías y rastreo</h3>
                <p>Consulta de guías generadas, documentos PDF y seguimiento de envíos.</p>
            </div>

            <div class="card">
                <h3>Incidencias</h3>
                <p>Atención a problemas con recolección, entrega, datos capturados o generación de guía.</p>
            </div>
        </div>

        <a class="cta" href="https://wa.me/5210000000000" target="_blank">Contactar por WhatsApp</a>
    </div>
</div>
</body>
</html>