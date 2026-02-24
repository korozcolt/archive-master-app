<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Notificaciones') }}
            </h2>
            <div class="flex space-x-2">
                <form method="POST" action="{{ route('notifications.markAllAsRead') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Marcar todas como leídas
                    </button>
                </form>

                <form method="POST" action="{{ route('notifications.clearRead') }}" onsubmit="return confirm('¿Estás seguro de eliminar todas las notificaciones leídas?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 active:bg-red-900 focus:outline-none focus:border-red-900 focus:ring ring-red-300 disabled:opacity-25 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Limpiar leídas
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if($notifications->count() > 0)
                        <div class="mb-4 flex justify-between items-center">
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Total: <span class="font-semibold">{{ $notifications->total() }}</span> notificaciones
                                @if($unreadCount > 0)
                                    | <span class="font-semibold text-blue-600 dark:text-blue-400">{{ $unreadCount }}</span> sin leer
                                @endif
                            </p>
                        </div>

                        <div class="space-y-3">
                            @foreach($notifications as $notification)
                                @php
                                    $ui = $presentedNotifications[$notification->id] ?? null;
                                    $data = $notification->data;
                                    $isUnread = is_null($notification->read_at);
                                    $color = $ui['color'] ?? $data['color'] ?? 'gray';

                                    $colorClasses = [
                                        'blue' => 'bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-800',
                                        'green' => 'bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-800',
                                        'yellow' => 'bg-yellow-50 border-yellow-200 dark:bg-yellow-900/20 dark:border-yellow-800',
                                        'red' => 'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-800',
                                        'orange' => 'bg-orange-50 border-orange-200 dark:bg-orange-900/20 dark:border-orange-800',
                                        'gray' => 'bg-gray-50 border-gray-200 dark:bg-gray-900/20 dark:border-gray-700',
                                    ];

                                    $iconColorClasses = [
                                        'blue' => 'text-blue-600 bg-blue-100 dark:text-blue-400 dark:bg-blue-900',
                                        'green' => 'text-green-600 bg-green-100 dark:text-green-400 dark:bg-green-900',
                                        'yellow' => 'text-yellow-600 bg-yellow-100 dark:text-yellow-400 dark:bg-yellow-900',
                                        'red' => 'text-red-600 bg-red-100 dark:text-red-400 dark:bg-red-900',
                                        'orange' => 'text-orange-600 bg-orange-100 dark:text-orange-400 dark:bg-orange-900',
                                        'gray' => 'text-gray-600 bg-gray-100 dark:text-gray-400 dark:bg-gray-900',
                                    ];
                                @endphp

                                <div class="border rounded-lg p-4 {{ $colorClasses[$color] ?? $colorClasses['gray'] }} {{ $isUnread ? 'border-l-4' : '' }} transition-all hover:shadow-md">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-start flex-1">
                                            <!-- Icon -->
                                            <div class="flex-shrink-0">
                                                <div class="w-12 h-12 rounded-full flex items-center justify-center {{ $iconColorClasses[$color] ?? $iconColorClasses['gray'] }}">
                                                    @if((($ui['icon'] ?? null) ?? ($data['icon'] ?? 'bell')) === 'document')
                                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                                                        </svg>
                                                    @elseif((($ui['icon'] ?? null) ?? ($data['icon'] ?? 'bell')) === 'clock')
                                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                                        </svg>
                                                    @elseif((($ui['icon'] ?? null) ?? ($data['icon'] ?? 'bell')) === 'refresh')
                                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path>
                                                        </svg>
                                                    @elseif((($ui['icon'] ?? null) ?? ($data['icon'] ?? 'bell')) === 'warning')
                                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.72-1.36 3.486 0l6.518 11.591c.75 1.334-.213 2.99-1.742 2.99H3.48c-1.53 0-2.492-1.656-1.743-2.99L8.257 3.1zM11 14a1 1 0 10-2 0 1 1 0 002 0zm-1-7a1 1 0 00-1 1v3a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                        </svg>
                                                    @else
                                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"></path>
                                                        </svg>
                                                    @endif
                                                </div>
                                            </div>

                                            <!-- Content -->
                                            <div class="ml-4 flex-1">
                                                <div class="flex items-start justify-between">
                                                    <div>
                                                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                                            {{ $ui['title'] ?? $data['title'] ?? 'Notificación' }}
                                                            @if($isUnread)
                                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                                    Nuevo
                                                                </span>
                                                            @endif
                                                        </h3>
                                                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                                            {{ $ui['message'] ?? $data['message'] ?? '' }}
                                                        </p>

                                                        <!-- Additional Info -->
                                                        @if(isset($data['document_number']))
                                                            <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                                                                <span class="font-medium">Documento:</span> {{ $data['document_number'] }}
                                                            </p>
                                                        @endif
                                                        @if(!empty($ui['meta']) && is_array($ui['meta']))
                                                            <div class="mt-2 flex flex-wrap gap-2">
                                                                @foreach($ui['meta'] as $metaKey => $metaValue)
                                                                    <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[11px] text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                                                        <span class="mr-1 font-semibold">{{ $metaKey }}:</span>{{ $metaValue }}
                                                                    </span>
                                                                @endforeach
                                                            </div>
                                                        @endif

                                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">
                                                            {{ $notification->created_at->diffForHumans() }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <!-- Actions -->
                                                <div class="mt-3 flex items-center space-x-3">
                                                    @if(isset($ui['action_url']) || isset($data['action_url']))
                                                        <a href="{{ $ui['action_url'] ?? $data['action_url'] }}" class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                            </svg>
                                                            Ver documento
                                                        </a>
                                                    @endif

                                                    @if($isUnread)
                                                        <form method="POST" action="{{ route('notifications.markAsRead', $notification->id) }}" class="inline">
                                                            @csrf
                                                            <button type="submit" class="inline-flex items-center text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300">
                                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                                </svg>
                                                                Marcar como leída
                                                            </button>
                                                        </form>
                                                    @endif

                                                    <form method="POST" action="{{ route('notifications.destroy', $notification->id) }}" class="inline" onsubmit="return confirm('¿Eliminar esta notificación?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="inline-flex items-center text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                            </svg>
                                                            Eliminar
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Pagination -->
                        <div class="mt-6">
                            {{ $notifications->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Sin notificaciones</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No tienes ninguna notificación en este momento.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
