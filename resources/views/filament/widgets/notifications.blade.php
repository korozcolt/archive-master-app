<x-filament-widgets::widget>
    <x-filament::section>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- System Alerts -->
            <div class="lg:col-span-1">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    🚨 Alertas del Sistema
                </h3>
                <div class="space-y-3">
                    @forelse($alerts as $alert)
                        <div class="card-enhanced p-4 rounded-lg border transition-all duration-200 hover:shadow-md
                            @if($alert['type'] === 'warning') bg-yellow-50 border-yellow-200 dark:bg-yellow-900/10 dark:border-yellow-800
                            @elseif($alert['type'] === 'info') bg-blue-50 border-blue-200 dark:bg-blue-900/10 dark:border-blue-800
                            @else bg-gray-50 border-gray-200 dark:bg-gray-800 dark:border-gray-700
                            @endif">
                            <div class="flex items-start">
                                <div class="text-2xl mr-3">{{ $alert['icon'] }}</div>
                                <div class="flex-1">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-1">{{ $alert['title'] }}</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">{{ $alert['message'] }}</p>
                                    @if(isset($alert['action_url']))
                                        <a href="{{ $alert['action_url'] }}" 
                                           class="inline-flex items-center text-sm font-medium
                                            @if($alert['type'] === 'warning') text-yellow-700 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300
                                            @elseif($alert['type'] === 'info') text-blue-700 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300
                                            @else text-gray-700 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300
                                            @endif">
                                            {{ $alert['action_text'] }}
                                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                        </a>
                                    @endif
                                </div>
                                @if($alert['priority'] === 'high')
                                    <div class="ml-2">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400">
                                            Alta
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <div class="text-4xl mb-2">✅</div>
                            <p>No hay alertas del sistema</p>
                        </div>
                    @endforelse
                </div>
            </div>
            
            <!-- Recent Notifications -->
            <div class="lg:col-span-1">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    🔔 Notificaciones Recientes
                </h3>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    @forelse($notifications as $notification)
                        <div class="card-enhanced bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:shadow-md transition-all duration-200
                            @if(!$notification['read']) border-l-4 border-l-blue-500 @endif">
                            <div class="flex items-start">
                                <div class="text-xl mr-3">{{ $notification['icon'] }}</div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-1">
                                        <h4 class="font-semibold text-gray-900 dark:text-white text-sm">{{ $notification['title'] }}</h4>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $notification['time_human'] }}</span>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ $notification['message'] }}</p>
                                    @if(isset($notification['actor']))
                                        <p class="text-xs text-gray-500 dark:text-gray-500">Por: {{ $notification['actor'] }}</p>
                                    @endif
                                    @if(isset($notification['url']))
                                        <a href="{{ $notification['url'] }}" class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 mt-2">
                                            Ver detalles
                                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                        </a>
                                    @endif
                                </div>
                                @if($notification['priority'] === 'high')
                                    <div class="ml-2">
                                        <div class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <div class="text-4xl mb-2">📭</div>
                            <p>No hay notificaciones recientes</p>
                        </div>
                    @endforelse
                </div>
            </div>
            
            <!-- Reminders -->
            <div class="lg:col-span-1">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    📅 Recordatorios
                </h3>
                <div class="space-y-3">
                    @forelse($reminders as $reminder)
                        <div class="card-enhanced bg-gradient-to-r from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 p-4 rounded-lg border border-purple-200 dark:border-purple-700 hover:shadow-md transition-all duration-200">
                            <div class="flex items-start">
                                <div class="text-2xl mr-3">{{ $reminder['icon'] }}</div>
                                <div class="flex-1">
                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-1">{{ $reminder['title'] }}</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">{{ $reminder['message'] }}</p>
                                    @if(isset($reminder['action_url']))
                                        <a href="{{ $reminder['action_url'] }}" 
                                           class="inline-flex items-center text-sm font-medium text-purple-700 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300">
                                            {{ $reminder['action_text'] }}
                                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                        </a>
                                    @endif
                                </div>
                                <div class="ml-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                        @if($reminder['priority'] === 'high') bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400
                                        @elseif($reminder['priority'] === 'medium') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400
                                        @else bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400
                                        @endif">
                                        @if($reminder['priority'] === 'high') Alta
                                        @elseif($reminder['priority'] === 'medium') Media
                                        @else Baja
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <div class="text-4xl mb-2">🎯</div>
                            <p>No hay recordatorios pendientes</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
        
        <!-- Notification Actions -->
        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-wrap gap-3 justify-between items-center">
                <div class="flex flex-wrap gap-3">
                    <button onclick="markAllAsRead()" 
                            class="btn-primary-enhanced inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-all duration-200 hover:shadow-lg transform hover:-translate-y-0.5">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Marcar como Leídas
                    </button>
                    
                    <button onclick="refreshNotifications()" 
                            class="btn-primary-enhanced inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-all duration-200 hover:shadow-lg transform hover:-translate-y-0.5">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Actualizar
                    </button>
                </div>
                
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Última actualización: <span id="last-update">{{ now()->format('H:i:s') }}</span>
                </div>
            </div>
        </div>
    </x-filament::section>
    
    <script>
        function markAllAsRead() {
            // Simular marcar como leídas
            document.querySelectorAll('.border-l-blue-500').forEach(el => {
                el.classList.remove('border-l-4', 'border-l-blue-500');
            });
            showNotification('success', 'Notificaciones marcadas', 'Todas las notificaciones han sido marcadas como leídas.');
        }
        
        function refreshNotifications() {
            // Simular actualización
            const lastUpdate = document.getElementById('last-update');
            if (lastUpdate) {
                lastUpdate.textContent = new Date().toLocaleTimeString('es-ES', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            }
            showNotification('info', 'Notificaciones actualizadas', 'Las notificaciones han sido actualizadas.');
        }
        
        // Auto-refresh cada 5 minutos
        setInterval(() => {
            refreshNotifications();
        }, 300000);
    </script>
</x-filament-widgets::widget>