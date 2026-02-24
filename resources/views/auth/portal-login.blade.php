<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} - Acceso al Portal</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-white">
    <div class="relative min-h-screen overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(59,130,246,0.25),transparent_45%),radial-gradient(circle_at_85%_15%,rgba(14,165,233,0.18),transparent_40%),linear-gradient(180deg,#020617_0%,#0f172a_55%,#111827_100%)]"></div>
        <div class="relative mx-auto flex min-h-screen max-w-6xl items-center px-4 py-10 sm:px-6 lg:px-8">
            <div class="grid w-full items-start gap-6 lg:grid-cols-[1.05fr_1fr_1fr]">
                <div class="hidden rounded-2xl border border-white/10 bg-white/5 p-8 shadow-2xl backdrop-blur md:block">
                    <div class="inline-flex items-center rounded-full border border-blue-300/30 bg-blue-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-blue-200">
                        ArchiveMaster
                    </div>
                    <h1 class="mt-5 text-3xl font-semibold leading-tight text-white">Ingreso al Portal Documental</h1>
                    <p class="mt-3 text-sm leading-6 text-slate-300">
                        Usa acceso operativo si eres personal interno. Usa OTP si eres usuario final con un recibido.
                    </p>
                    <div class="mt-8 space-y-3 text-sm text-slate-200">
                        <div class="flex items-start gap-3 rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                            <span class="mt-0.5 h-2 w-2 rounded-full bg-emerald-400"></span>
                            <p><span class="font-semibold text-white">Operativo:</span> recepcionista, oficina y archivo con correo y contraseña.</p>
                        </div>
                        <div class="flex items-start gap-3 rounded-xl border border-white/10 bg-black/20 px-4 py-3">
                            <span class="mt-0.5 h-2 w-2 rounded-full bg-sky-400"></span>
                            <p><span class="font-semibold text-white">Usuario final:</span> acceso por número de recibido y OTP.</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/8 p-6 shadow-2xl backdrop-blur-xl">
                    <h2 class="text-2xl font-semibold text-white">Acceso Operativo</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-300">
                    Para recepcionista, oficina, archivo y usuarios internos del portal.
                    </p>

                    @error('password_login')
                        <div class="mt-4 rounded-xl border border-rose-300/40 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">
                            {{ $message }}
                        </div>
                    @enderror

                    <form method="POST" action="{{ route('portal.auth.password-login') }}" class="mt-6 space-y-4">
                        @csrf
                        <div>
                            <label for="portal_email" class="mb-1.5 block text-sm font-medium text-slate-200">Correo</label>
                            <input id="portal_email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"
                                class="block h-11 w-full rounded-xl border border-white/10 bg-slate-800/80 px-3.5 text-sm text-white shadow-inner outline-none ring-0 placeholder:text-slate-500 focus:border-sky-400/70 focus:bg-slate-800 focus:ring-2 focus:ring-sky-400/20">
                        </div>
                        <div>
                            <label for="portal_password" class="mb-1.5 block text-sm font-medium text-slate-200">Contraseña</label>
                            <input id="portal_password" name="password" type="password" required autocomplete="current-password"
                                class="block h-11 w-full rounded-xl border border-white/10 bg-slate-800/80 px-3.5 text-sm text-white shadow-inner outline-none ring-0 placeholder:text-slate-500 focus:border-sky-400/70 focus:bg-slate-800 focus:ring-2 focus:ring-sky-400/20">
                        </div>
                        <label class="flex items-center gap-2.5 text-sm text-slate-300">
                            <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-white/20 bg-slate-800 text-sky-500 focus:ring-sky-400/30">
                            Recordarme
                        </label>
                        <button type="submit"
                            class="inline-flex h-11 w-full items-center justify-center rounded-xl border border-sky-400/20 bg-gradient-to-r from-sky-500 to-blue-600 px-4 text-sm font-semibold text-white shadow-lg shadow-blue-900/30 transition hover:from-sky-400 hover:to-blue-500 focus:outline-none focus:ring-2 focus:ring-sky-300/40">
                            Ingresar con contraseña
                        </button>
                    </form>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/8 p-6 shadow-2xl backdrop-blur-xl">
                    <h2 class="text-2xl font-semibold text-white">Acceso por Recibido</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-300">
                        Para usuario final por recibido: ingresa número de recibido y correo para solicitar OTP.
                    </p>

                    <form method="POST" action="{{ route('portal.auth.request-otp') }}" class="mt-6 space-y-4">
                        @csrf
                        <div>
                            <label for="receipt_number" class="mb-1.5 block text-sm font-medium text-slate-200">Número de recibido</label>
                            <input id="receipt_number" name="receipt_number" type="text" value="{{ old('receipt_number') }}" required
                                class="block h-11 w-full rounded-xl border border-white/10 bg-slate-800/80 px-3.5 text-sm text-white shadow-inner outline-none ring-0 placeholder:text-slate-500 focus:border-blue-400/70 focus:bg-slate-800 focus:ring-2 focus:ring-blue-400/20">
                            @error('receipt_number')
                                <p class="mt-1.5 text-sm text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-200">Correo</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required
                                class="block h-11 w-full rounded-xl border border-white/10 bg-slate-800/80 px-3.5 text-sm text-white shadow-inner outline-none ring-0 placeholder:text-slate-500 focus:border-blue-400/70 focus:bg-slate-800 focus:ring-2 focus:ring-blue-400/20">
                            @error('email')
                                <p class="mt-1.5 text-sm text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit"
                            class="inline-flex h-11 w-full items-center justify-center rounded-xl border border-blue-400/20 bg-gradient-to-r from-blue-500 to-indigo-600 px-4 text-sm font-semibold text-white shadow-lg shadow-indigo-900/30 transition hover:from-blue-400 hover:to-indigo-500 focus:outline-none focus:ring-2 focus:ring-blue-300/40">
                            Solicitar OTP
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
