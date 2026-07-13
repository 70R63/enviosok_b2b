<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>FAQ | ZIGO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: #111827;
            background: #f8fafc;
        }

        .page-header {
            background: linear-gradient(135deg, #2563eb, #4f46e5);
            color: white;
            padding: 50px 24px;
            text-align: center;
        }

        .page-header h1 {
            font-size: 42px;
            margin: 0 0 12px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
            padding: 50px 24px;
        }

        .faq-item {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 16px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
        }

        .faq-item h3 {
            margin-top: 0;
            color: #1d4ed8;
        }

        .back {
            display: inline-block;
            margin-top: 20px;
            color: #2563eb;
            font-weight: 900;
            text-decoration: none;
        }
    </style>
</head>
<body>

<header class="page-header">
    <h1>Preguntas frecuentes</h1>
    <p>Consulta las dudas más comunes sobre cotización, registro, pago, guías y soluciones empresariales.</p>
</header>

<main class="container">

    <div class="faq-item">
        <h3>¿Puedo cotizar sin registrarme?</h3>
        <p>Sí. Puedes consultar una cotización inicial como visitante. Algunas funciones, como continuar con ciertos tipos de envío, pagar o generar una guía, pueden requerir iniciar sesión.</p>
    </div>

    <div class="faq-item">
        <h3>¿Qué datos necesito para cotizar?</h3>
        <p>Necesitas código postal origen, código postal destino, tipo de envío, peso y medidas del paquete.</p>
    </div>

    <div class="faq-item">
        <h3>¿Qué tipo de envíos puedo realizar?</h3>
        <p>La plataforma está preparada para operar envíos tipo sobre y caja, de acuerdo con las restricciones y servicios disponibles configurados en ZIGO.</p>
    </div>

    <div class="faq-item">
        <h3>¿Cómo se calcula el costo del envío?</h3>
        <p>El costo se calcula con base en la tarifa del proveedor logístico, el tipo de envío, cobertura, peso, dimensiones y reglas comerciales configuradas por ZIGO.</p>
    </div>

    <div class="faq-item">
        <h3>¿El valor declarado afecta el precio?</h3>
        <p>Puede afectar el precio cuando se consideran seguros, coberturas o reglas adicionales relacionadas con el valor del contenido declarado.</p>
    </div>

    <div class="faq-item">
        <h3>¿Cómo obtengo mi guía?</h3>
        <p>Después de completar el flujo de cotización y pago, la plataforma genera la guía correspondiente para que puedas descargarla y usarla en tu envío.</p>
    </div>

    <div class="faq-item">
        <h3>¿Puedo consultar el seguimiento de mi envío?</h3>
        <p>Sí. ZIGO contempla consulta de seguimiento para revisar el estado del envío con base en la información disponible.</p>
    </div>

    <div class="faq-item">
        <h3>¿ZIGO ofrece soluciones para empresas?</h3>
        <p>Sí. ZIGO cuenta con un enfoque B2B para empresas que requieren operación con mayor volumen, clientes, usuarios, reportes, saldos y administración logística.</p>
    </div>

    <div class="faq-item">
        <h3>¿Puedo integrar ZIGO con mi sistema?</h3>
        <p>Sí. API Hub está pensado para exponer servicios que permitan integrar funcionalidades de ZIGO con sistemas externos, iniciando con consultas como códigos postales y colonias.</p>
    </div>

    <a class="back" href="{{ url('/') }}">← Volver al inicio</a>

</main>

</body>
</html>