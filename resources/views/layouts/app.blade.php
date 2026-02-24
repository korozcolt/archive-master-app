<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - @yield('title', 'Dashboard')</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen bg-slate-100 dark:bg-slate-950">
        <!-- Navigation -->
        <nav class="sticky top-0 z-40 border-b border-white/70 bg-white/80 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/85">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <!-- Logo -->
                        <div class="shrink-0 flex items-center">
                            @php
                                $portalRoles = ['office_manager', 'archive_manager', 'receptionist', 'regular_user'];
                                $isPortalUser = Auth::user()?->hasAnyRole($portalRoles);
                            @endphp
                            <a href="{{ $isPortalUser ? route('portal.dashboard') : route('dashboard') }}" class="inline-flex items-center gap-2 text-xl font-semibold tracking-tight text-slate-900 dark:text-white">
                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-sky-500 to-indigo-600 text-sm font-bold text-white shadow-sm">A</span>
                                <span>ArchiveMaster</span>
                            </a>
                        </div>

                        <!-- Navigation Links -->
                        <div class="hidden items-center gap-2 sm:ml-8 sm:flex">
                            <a href="{{ $isPortalUser ? route('portal.dashboard') : route('dashboard') }}" class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('dashboard') || request()->routeIs('portal.dashboard') ? 'bg-sky-50 text-sky-700 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/20' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                                Dashboard
                            </a>
                            @if ($isPortalUser)
                                <a href="{{ route('portal.reports') }}" class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('portal.reports') ? 'bg-sky-50 text-sky-700 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/20' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                                    Reportes
                                </a>
                            @endif
                            <a href="{{ route('documents.index') }}" class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('documents.*') ? 'bg-sky-50 text-sky-700 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/20' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                                Mis Documentos
                            </a>
                        </div>
                    </div>

                    <!-- Right Side: Notifications + User -->
                    <div class="flex items-center gap-2 sm:gap-3">
                        <!-- Notifications Bell -->
                        <div class="relative" x-data="{ open: false, count: {{ Auth::user()->unreadNotifications()->count() }}, notifications: [] }"
                             x-init="
                                 // Load notifications on init
                                 fetch('{{ route('notifications.unread') }}')
                                     .then(res => res.json())
                                     .then(data => {
                                         notifications = data.notifications;
                                         count = data.count;
                                     });

                                 // Poll for new notifications every 30 seconds
                                 setInterval(() => {
                                     fetch('{{ route('notifications.unread') }}')
                                         .then(res => res.json())
                                         .then(data => {
                                             notifications = data.notifications;
                                             count = data.count;
                                         });
                                 }, 30000);
                             ">
                            <button @click="open = !open" class="relative rounded-xl border border-slate-200 bg-white p-2 text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-300/40 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white">
                                <!-- Bell Icon -->
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                </svg>
                                <!-- Counter Badge -->
                                <span x-show="count > 0" x-text="count" class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full"></span>
                            </button>

                            <!-- Notifications Dropdown -->
                            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-12 w-96 bg-white dark:bg-gray-800 rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 z-50">
                                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Notificaciones</h3>
                                    <a href="{{ route('notifications.index') }}" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">Ver todas</a>
                                </div>

                                <div class="max-h-96 overflow-y-auto">
                                    <template x-if="notifications.length === 0">
                                        <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                                            <svg class="w-12 h-12 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                            </svg>
                                            <p>No tienes notificaciones nuevas</p>
                                        </div>
                                    </template>

                                    <template x-for="notification in notifications" :key="notification.id">
                                        <a :href="notification.action_url" class="block p-4 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                            <div class="flex items-start">
                                                <div class="flex-shrink-0">
                                                    <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                                         :class="{
                                                             'bg-blue-100 text-blue-600': notification.color === 'blue',
                                                             'bg-yellow-100 text-yellow-600': notification.color === 'yellow',
                                                             'bg-red-100 text-red-600': notification.color === 'red',
                                                             'bg-green-100 text-green-600': notification.color === 'green',
                                                             'bg-gray-100 text-gray-600': notification.color === 'gray'
                                                         }">
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                                        </svg>
                                                    </div>
                                                </div>
                                                <div class="ml-3 flex-1">
                                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="notification.title"></p>
                                                    <p class="text-sm text-gray-600 dark:text-gray-400" x-text="notification.message"></p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-1" x-text="notification.created_at"></p>
                                                </div>
                                            </div>
                                        </a>
                                    </template>
                                </div>

                                <template x-if="count > 0">
                                    <div class="p-3 border-t border-gray-200 dark:border-gray-700">
                                        <form method="POST" action="{{ route('notifications.markAllAsRead') }}">
                                            @csrf
                                            <button type="submit" class="w-full text-center text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                Marcar todas como leídas
                                            </button>
                                        </form>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- User Info -->
                        <span class="hidden max-w-44 truncate rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm sm:inline-block dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ Auth::user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-300/30 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
                                Cerrar Sesión
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Heading -->
        @if (isset($header))
            <header class="bg-white dark:bg-gray-800 shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endif

        <!-- Page Content -->
        <main class="py-8 sm:py-10">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Success Message -->
                @if (session('success'))
                    <div class="mb-4 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-emerald-800 shadow-sm dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200" role="alert">
                        <span class="block sm:inline">{{ session('success') }}</span>
                    </div>
                @endif

                <!-- Error Messages -->
                @if ($errors->any())
                    <div class="mb-4 rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-rose-800 shadow-sm dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200" role="alert">
                        <ul class="list-inside list-disc">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @isset($slot)
                    {{ $slot }}
                @else
                    @yield('content')
                @endisset
            </div>
        </main>
    </div>

    <!-- Alpine.js for notifications dropdown -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @livewireScripts
</body>
</html>
