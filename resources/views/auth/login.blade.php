<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <title>Iniciar sesión - ZIGO</title>
    <link
        rel="stylesheet"
        href="{{ asset('css/zigo-auth.css') }}"
    >
</head>
<body>
<div class="zigo-auth-container">
    <div class="zigo-auth-card">
        <section class="zigo-auth-side">
            <h1>Bienvenido</h1>
            <p>
                Inicia sesión para consultar tus envíos y
                agilizar futuras cotizaciones.
            </p>

            <div class="zigo-auth-brand">
                <img
                    src="{{ asset('img/zigo-logo.png') }}"
                    alt="ZIGO"
                >
                <div class="zigo-auth-tagline">
                    Tecnología • Logística • Conexión
                </div>
            </div>
        </section>

        <main class="zigo-auth-form">
            <h2>Iniciar sesión</h2>

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
                action="{{ route('login') }}"
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
                    >
                </div>

                <div class="zigo-auth-group">
                    <label for="password">
                        Contraseña
                    </label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <button
                    type="submit"
                    class="zigo-auth-button"
                >
                    Ingresar
                </button>
            </form>

            <div class="zigo-auth-links">
                <a href="{{ route('password.request') }}">
                    ¿Olvidaste tu contraseña?
                </a>

                <span>
                    ¿No tienes cuenta?
                    <a href="{{ route('b2c.register') }}">
                        Regístrate
                    </a>
                </span>
            </div>
        </main>
    </div>
</div>
</body>
</html>