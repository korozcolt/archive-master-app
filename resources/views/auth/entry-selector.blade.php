<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} - Ingreso</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-white">
    <div class="relative min-h-screen overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(59,130,246,0.25),transparent_45%),radial-gradient(circle_at_85%_15%,rgba(245,158,11,0.16),transparent_40%),linear-gradient(180deg,#020617_0%,#0f172a_55%,#111827_100%)]"></div>
        <div class="relative mx-auto flex min-h-screen max-w-5xl items-center px-4 py-10 sm:px-6 lg:px-8">
            <div class="w-full rounded-3xl border border-white/10 bg-white/5 p-6 shadow-2xl backdrop-blur-xl sm:p-8">
                <div class="mb-6 inline-flex items-center rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-200">
                    ArchiveMaster
                </div>

                <div class="grid gap-6 lg:grid-cols-[1.1fr_1fr_1fr]">
                    <div class="rounded-2xl border border-white/10 bg-black/20 p-6">
                        <h1 class="text-3xl font-semibold leading-tight text-white">Selecciona el tipo de ingreso</h1>
                        <p class="mt-3 text-sm leading-6 text-slate-300">
                            Usa <span class="font-semibold text-white">Portal</span> para operación diaria (recepción, oficina, archivo y usuarios del portal).
                            Usa <span class="font-semibold text-white">Administrador</span> para configuración y gobierno del sistema.
                        </p>
                    </div>

                    <a href="{{ route('login') }}"
                        class="group rounded-2xl border border-sky-300/20 bg-sky-400/10 p-6 shadow-lg shadow-sky-950/30 transition hover:border-sky-300/40 hover:bg-sky-400/15">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-200">Portal</div>
                        <div class="mt-3 text-2xl font-semibold text-white">Ir al Portal</div>
                        <p class="mt-2 text-sm leading-6 text-slate-200">
                            Para recepcionista, oficina, archivo y usuarios con acceso por contraseña u OTP.
                        </p>
                        <div class="mt-5 inline-flex items-center gap-2 text-sm font-medium text-sky-200">
                            Abrir acceso portal
                            <span aria-hidden="true" class="transition group-hover:translate-x-0.5">→</span>
                        </div>
                    </a>

                    <a href="{{ url('/admin/login') }}"
                        class="group rounded-2xl border border-amber-300/20 bg-amber-400/10 p-6 shadow-lg shadow-amber-950/30 transition hover:border-amber-300/40 hover:bg-amber-400/15">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-200">Administrador</div>
                        <div class="mt-3 text-2xl font-semibold text-white">Ir a Administrador</div>
                        <p class="mt-2 text-sm leading-6 text-slate-200">
                            Para super administradores, administradores y administración por sucursal.
                        </p>
                        <div class="mt-5 inline-flex items-center gap-2 text-sm font-medium text-amber-200">
                            Abrir acceso administrador
                            <span aria-hidden="true" class="transition group-hover:translate-x-0.5">→</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
