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
<body class="font-sans antialiased motion-safe:animate-fade-in motion-safe:animate-duration-300 am-motion-safe">
    <div class="min-h-screen bg-slate-100 dark:bg-slate-950">
        <!-- Navigation -->
        <nav class="sticky top-0 z-40 border-b border-white/70 bg-white/80 backdrop-blur-xl motion-safe:animate-slide-in-top motion-safe:animate-duration-300 dark:border-slate-800 dark:bg-slate-900/85 am-motion-safe">
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
                        @php
                            $initialUnreadNotifications = Auth::user()->unreadNotifications()
                                ->take(10)
                                ->get()
                                ->map(fn ($notification) => \App\Support\NotificationPresenter::present($notification))
                                ->values();
                        @endphp
                        <div class="relative"
                             x-data="{
                                 open: false,
                                 count: {{ Auth::user()->unreadNotifications()->count() }},
                                 notifications: @js($initialUnreadNotifications),
                                 userId: {{ (int) Auth::id() }},
                                 realtimeChannel: null,
                                 refreshNotifications() {
                                     fetch('{{ route('notifications.unread') }}')
                                         .then((res) => res.json())
                                         .then((data) => {
                                             this.notifications = data.notifications;
                                             this.count = data.count;
                                         })
                                         .catch(() => {
                                             // Keep server-rendered notifications if request fails.
                                         });
                                 },
                                 bootstrapRealtime() {
                                     if (!window.ArchiveMasterRealtime) {
                                         return;
                                     }

                                     this.realtimeChannel = window.ArchiveMasterRealtime.subscribeToUserNotifications(
                                         this.userId,
                                         () => this.refreshNotifications(),
                                     );
                                 },
                             }"
                             x-init="
                                 refreshNotifications();
                                 setInterval(() => refreshNotifications(), 30000);
                                 bootstrapRealtime();
                             ">
                            <button @click="open = !open" class="relative rounded-xl border border-slate-200/80 bg-white/90 p-2 text-slate-600 shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-50 hover:text-slate-900 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-sky-300/40 motion-safe:hover:animate-pulse-fade-in motion-safe:hover:animate-duration-200 dark:border-slate-700 dark:bg-slate-800/90 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white am-motion-safe">
                                <!-- Bell Icon -->
                                <x-icons.archive-bell class="h-6 w-6" />
                                <!-- Counter Badge -->
                                <span x-show="count > 0" x-text="count" class="absolute top-0 right-0 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-gradient-to-r from-rose-500 to-red-500 px-1.5 text-[10px] font-bold leading-none text-white shadow-sm ring-2 ring-white/90 -translate-y-1/2 translate-x-1/2 dark:ring-slate-900"></span>
                            </button>

                            <!-- Notifications Dropdown -->
                            <div x-show="open" @click.away="open = false" class="am-dropdown-panel absolute right-0 z-50 mt-12 w-96 overflow-hidden rounded-2xl border border-slate-200/80 bg-white/95 shadow-xl ring-1 ring-slate-900/5 backdrop-blur-xl motion-safe:animate-fade-in-down motion-safe:animate-duration-200 dark:border-slate-700 dark:bg-slate-900/95 dark:ring-white/10 am-motion-safe">
                                <div class="flex items-center justify-between border-b border-slate-200/80 px-4 py-3 dark:border-slate-700">
                                    <h3 class="text-sm font-semibold uppercase tracking-[0.14em] text-slate-700 dark:text-slate-200">Notificaciones</h3>
                                    <a href="{{ route('notifications.index') }}" class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-600 transition hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300">Ver todas</a>
                                </div>

                                <div class="max-h-96 overflow-y-auto bg-gradient-to-b from-white to-slate-50/60 dark:from-slate-900 dark:to-slate-900/70">
                                    <template x-if="notifications.length === 0">
                                        <div class="p-8 text-center text-slate-500 dark:text-slate-400">
                                            <x-icons.archive-inbox class="mx-auto mb-2 h-12 w-12 text-slate-300 dark:text-slate-600" />
                                            <p class="text-sm">No tienes notificaciones nuevas</p>
                                        </div>
                                    </template>

                                    <template x-for="notification in notifications" :key="notification.id">
                                        <a :href="notification.action_url" class="group block border-b border-slate-200/70 px-4 py-3 transition hover:bg-slate-100/70 dark:border-slate-700 dark:hover:bg-slate-800/60">
                                            <div class="flex items-start">
                                                <div class="flex-shrink-0">
                                                    <div class="flex h-10 w-10 items-center justify-center rounded-xl shadow-sm"
                                                         :class="{
                                                             'bg-blue-100 text-blue-600 ring-1 ring-blue-200 dark:bg-blue-500/15 dark:text-blue-300 dark:ring-blue-500/30': notification.color === 'blue',
                                                             'bg-amber-100 text-amber-600 ring-1 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30': notification.color === 'yellow',
                                                             'bg-rose-100 text-rose-600 ring-1 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30': notification.color === 'red',
                                                             'bg-emerald-100 text-emerald-600 ring-1 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30': notification.color === 'green',
                                                             'bg-slate-100 text-slate-600 ring-1 ring-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:ring-slate-600': notification.color === 'gray'
                                                         }">
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                                        </svg>
                                                    </div>
                                                </div>
                                                <div class="ml-3 flex-1">
                                                    <p class="text-sm font-semibold text-slate-800 transition group-hover:text-slate-950 dark:text-slate-100 dark:group-hover:text-white" x-text="notification.title"></p>
                                                    <p class="text-sm text-slate-600 dark:text-slate-400" x-text="notification.message"></p>
                                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-500" x-text="notification.created_at"></p>
                                                </div>
                                            </div>
                                        </a>
                                    </template>
                                </div>

                                <template x-if="count > 0">
                                    <div class="border-t border-slate-200/80 p-3 dark:border-slate-700">
                                        <form method="POST" action="{{ route('notifications.markAllAsRead') }}">
                                            @csrf
                                            <button type="submit" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-center text-xs font-semibold uppercase tracking-[0.14em] text-slate-600 transition hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-sky-500/30 dark:hover:bg-sky-500/10 dark:hover:text-sky-300">
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
        <main class="py-8 sm:py-10 motion-safe:animate-fade-in-up motion-safe:animate-duration-300 am-motion-safe">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                @if (session('success'))
                    <x-ui.alert type="success" title="Operación completada" class="mb-4 motion-safe:animate-slide-in-right motion-safe:animate-duration-300 am-motion-safe">
                        {{ session('success') }}
                    </x-ui.alert>
                @endif

                @if (session('error'))
                    <x-ui.alert type="danger" title="No fue posible completar la acción" class="mb-4 motion-safe:animate-slide-in-right motion-safe:animate-duration-300 am-motion-safe">
                        {{ session('error') }}
                    </x-ui.alert>
                @endif

                @if (session('warning'))
                    <x-ui.alert type="warning" title="Atención" class="mb-4 motion-safe:animate-slide-in-right motion-safe:animate-duration-300 am-motion-safe">
                        {{ session('warning') }}
                    </x-ui.alert>
                @endif

                @if (session('info'))
                    <x-ui.alert type="info" title="Información" class="mb-4 motion-safe:animate-slide-in-right motion-safe:animate-duration-300 am-motion-safe">
                        {{ session('info') }}
                    </x-ui.alert>
                @endif

                @if ($errors->any())
                    <x-ui.alert type="danger" title="Revisa los datos enviados" class="mb-4 motion-safe:animate-shake motion-safe:animate-duration-300 am-motion-safe">
                        <ul class="space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>• {{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-ui.alert>
                @endif

                @isset($slot)
                    {{ $slot }}
                @else
                    @yield('content')
                @endisset
            </div>
        </main>
    </div>

    @livewireScripts
</body>
</html>
