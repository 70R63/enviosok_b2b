<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">
    @vite('resources/css/app.css')



<!-- SPRUHA -->
        <!-- spruha -->
        <meta charset="utf-8">
        <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
        <!-- Favicon -->
        <link rel="icon" href="{{ url('spruha/img/brand/favicon.ico') }}" type="image/x-icon"/>

        <!-- CSRF Token -->
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <!-- Title -->
        <title>{{ config('app.name', 'Laravel') }} - Plataforma de envios</title>

        <!-- Bootstrap css-->
        <link href="{{ url('spruha/plugins/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet"/ type="text/css">

        <!-- Icons css-->
        <link href="{{ url('spruha/plugins/web-fonts/icons.css') }}"  rel="stylesheet"/>
        <link href="{{ url('spruha/plugins/web-fonts/font-awesome/font-awesome.min.css') }}"  rel="stylesheet">
        <link href="{{ url('spruha/plugins/web-fonts/plugin.css') }}"  rel="stylesheet"/>

        <!-- Style css-->
        <link href="{{ url('spruha/css/style.css') }}"  rel="stylesheet">
        <link href="{{ url('spruha/css/colors/color6.css') }}"  rel="stylesheet">

        <!-- Select2 css-->
        <link href="{{ url('spruha/plugins/select2/css/select2.min.css') }}"  rel="stylesheet">

        <!-- Mutipleselect css-->
        <link rel="stylesheet" href="{{ url('spruha/plugins/multipleselect/multiple-select.css') }}">
        <style>
            html, body {
                height: 100%; /* Make sure the page takes up the full content height */
            }

            body {
                display: flex; /* Use flexbox to structure the layout */
                flex-direction: column; /* Stack elements vertically */
            }
            main {
                flex-grow: 1; /* Allows main content to grow and fill available space */
            }
            footer {
                margin-top: auto; /* Automatically margins the footer at the bottom */
            }
        </style>


<!-- ---------- -->
</head>
<body>
    <div id="app" class="d-flex flex-column h-100">
        <main class="py-4">
            @yield('content')
        </main>
        <footer class="mt-auto text-center text-lg-start main-footer">
            <div class="text-center">
                <span>Copyright © 2022 <a href="#">ULALAXPRESS</a>. Designed by <a href="#">TED</a> All rights reserved.</span>
            </div>
        </footer>
    </div>
</body>
@vite('resources/js/app.js')
<script>
    var url_base = '{{url('/')}}';
    var token = document.head.querySelector('meta[name="csrf-token"]');
</script>
</html>
