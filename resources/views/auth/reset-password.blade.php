<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <title>Nueva contraseña - ZIGO</title>
    <link
        rel="stylesheet"
        href="{{ asset('css/zigo-auth.css') }}"
    >
</head>
<body>
<div class="zigo-auth-container">
    <div class="zigo-auth-card">
        <section class="zigo-auth-side">
            <h1>Crea una nueva contraseña</h1>
            <p>
                Elige una contraseña segura que no utilices
                en otros servicios.
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
            <h2>Nueva contraseña</h2>
            <p class="zigo-auth-description">
                La liga de recuperación tiene una vigencia
                de 60 minutos y solo puede utilizarse una vez.
            </p>

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
                action="{{ route('password.update') }}"
            >
                @csrf

                <input
                    type="hidden"
                    name="token"
                    value="{{ $request->route('token') }}"
                >

                <div class="zigo-auth-group">
                    <label for="email">
                        Correo electrónico
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email', $request->email) }}"
                        required
                        autofocus
                        autocomplete="email"
                    >
                </div>

                <div class="zigo-auth-group">
                    <label for="password">
                        Nueva contraseña
                    </label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="new-password"
                    >
                    <div class="zigo-auth-help">
                        Usa al menos 8 caracteres y evita
                        datos fáciles de adivinar.
                    </div>
                </div>

                <div class="zigo-auth-group">
                    <label for="password_confirmation">
                        Confirmar nueva contraseña
                    </label>
                    <input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                    >
                </div>

                <button
                    type="submit"
                    class="zigo-auth-button"
                >
                    Cambiar contraseña
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