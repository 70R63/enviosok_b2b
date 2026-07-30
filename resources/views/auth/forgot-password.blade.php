<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <title>Recuperar contraseña - ZIGO</title>
    <link
        rel="stylesheet"
        href="{{ asset('css/zigo-auth.css') }}"
    >
</head>
<body>
<div class="zigo-auth-container">
    <div class="zigo-auth-card">
        <section class="zigo-auth-side">
            <h1>Recupera tu acceso</h1>
            <p>
                Te enviaremos una liga segura para crear
                una nueva contraseña.
            </p>

            <div class="zigo-auth-brand">
                <img
                    src="{{ asset('img/zigo-logo.png') }}"
                    alt="ZIGO"
                >
                <div class="zigo-auth-tagline">
                    Seguridad • Confianza • Control
                </div>
            </div>
        </section>

        <main class="zigo-auth-form">
            <h2>Restablecer contraseña</h2>
            <p class="zigo-auth-description">
                Escribe el correo asociado a tu cuenta ZIGO.
                Por seguridad, la respuesta será la misma
                exista o no una cuenta registrada.
            </p>

            @if (session('status'))
                <div
                    class="zigo-auth-alert
                           zigo-auth-alert-success"
                >
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div
                    class="zigo-auth-alert
                           zigo-auth-alert-error"
                >
                    {{ $errors->first() }}
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('password.email') }}"
            >
                @csrf

                <div class="zigo-auth-group">
                    <label for="email">
                        Correo electrónico
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="nombre@empresa.com"
                    >
                </div>

                <button
                    type="submit"
                    class="zigo-auth-button"
                >
                    Enviar liga de recuperación
                </button>
            </form>

            <div class="zigo-auth-links">
                <a href="{{ route('login') }}">
                    Volver a iniciar sesión
                </a>
            </div>
        </main>
    </div>
</div>
</body>
</html>