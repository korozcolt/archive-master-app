<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ArchiveMaster - Sistema de Gestión Documental</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/welcome.jsx'])

    <!-- Meta Tags for SEO -->
    <meta name="description" content="ArchiveMaster - Sistema empresarial completo de gestión documental desarrollado con Laravel 12 + Filament 3">
    <meta name="keywords" content="gestión documental, Laravel, Filament, workflows, documentos, empresarial">
    <meta name="author" content="ArchiveMaster">

    <!-- Open Graph -->
    <meta property="og:title" content="ArchiveMaster - Sistema de Gestión Documental">
    <meta property="og:description" content="La solución completa para la gestión documental empresarial">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:image" content="{{ asset('images/logo_archive_master.png') }}">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('images/logo_archive_master.png') }}">

    <!-- Additional Styles for Grid Pattern -->
    <style>
        .bg-grid-pattern {
            background-image:
                linear-gradient(rgba(255,255,255,.1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.1) 1px, transparent 1px);
            background-size: 20px 20px;
        }
    </style>
</head>
<body class="antialiased">
    <!-- Contenedor para React -->
    <div id="welcome-app"></div>

    <!-- Script para pasar datos de Laravel a React -->
    <script>
        window.appData = {
            isAuthenticated: {{ auth()->check() ? 'true' : 'false' }},
            user: @json(auth()->user()),
            adminUrl: '/admin',
            loginUrl: '/admin/login',
            logoUrl: '{{ asset("images/logo_archive_master.png") }}',
            appUrl: '{{ url("/") }}',
            stats: {
                resources: 14,
                widgets: 25,
                endpoints: 50,
                users: 1000
            }
        };
    </script>
</body>
</html>
