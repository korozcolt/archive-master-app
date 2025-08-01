<x-filament-widgets::widget>
    <x-filament::section>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Quick Stats -->
            <div class="lg:col-span-1">
                <x-filament::section.heading>
                    📊 Estadísticas Rápidas
                </x-filament::section.heading>
                <div class="grid grid-cols-2 gap-3 mt-4">
                    <x-filament::card class="p-4">
                        <div class="text-2xl font-bold text-primary-600">{{ $quickStats['my_pending'] }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Mis Pendientes</div>
                    </x-filament::card>
                    <x-filament::card class="p-4">
                        <div class="text-2xl font-bold text-success-600">{{ $quickStats['my_completed_today'] }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Completados Hoy</div>
                    </x-filament::card>
                    <x-filament::card class="p-4">
                        <div class="text-2xl font-bold text-info-600">{{ $quickStats['company_total'] }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Total Empresa</div>
                    </x-filament::card>
                    <x-filament::card class="p-4">
                        <div class="text-2xl font-bold text-warning-600">{{ $quickStats['company_pending'] }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Pendientes Empresa</div>
                    </x-filament::card>
                </div>
                
                <!-- Keyboard Shortcuts -->
                <div class="mt-6">
                    <x-filament::section.heading>
                        ⌨️ Atajos de Teclado
                    </x-filament::section.heading>
                    <div class="space-y-2 mt-4">
                        @foreach($shortcuts as $shortcut)
                            <x-filament::card class="p-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ $shortcut['action'] }}</span>
                                    <x-filament::badge color="gray" size="sm">
                                        {{ $shortcut['key'] }}
                                    </x-filament::badge>
                                </div>
                            </x-filament::card>
                        @endforeach
                    </div>
                </div>
            </div>
            
            <!-- Pending Documents -->
            <div class="lg:col-span-1">
                <x-filament::section.heading>
                    📋 Mis Documentos Pendientes
                </x-filament::section.heading>
                <div class="space-y-3 max-h-96 overflow-y-auto mt-4">
                    @forelse($pendingDocuments as $document)
                        <x-filament::card class="p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <a href="{{ $document['url'] }}" class="text-sm font-semibold text-primary-600 hover:underline">
                                            {{ $document['number'] }}
                                        </a>
                                        @if($document['is_overdue'])
                                            <x-filament::badge color="danger" size="sm">
                                                ⚠️ Vencido
                                            </x-filament::badge>
                                        @endif
                                    </div>
                                    <p class="text-sm font-medium mb-1">{{ $document['title'] }}</p>
                                    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <span>{{ $document['category'] }}</span>
                                        <span>•</span>
                                        <span>{{ $document['created_at'] }}</span>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <x-filament::badge :color="$document['status_color']" size="sm">
                                        {{ $document['status'] }}
                                    </x-filament::badge>
                                </div>
                            </div>
                        </x-filament::card>
                    @empty
                        <x-filament::card class="text-center py-8">
                            <div class="text-4xl mb-2">🎉</div>
                            <p class="text-gray-500 dark:text-gray-400">¡No tienes documentos pendientes!</p>
                        </x-filament::card>
                    @endforelse
                </div>
            </div>
            
            <!-- Recent Documents -->
            <div class="lg:col-span-1">
                <x-filament::section.heading>
                    🕒 Documentos Recientes
                </x-filament::section.heading>
                <div class="space-y-3 max-h-96 overflow-y-auto mt-4">
                    @forelse($recentDocuments as $document)
                        <x-filament::card class="p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="mb-2">
                                        <a href="{{ $document['url'] }}" class="text-sm font-semibold text-primary-600 hover:underline">
                                            {{ $document['number'] }}
                                        </a>
                                    </div>
                                    <p class="text-sm font-medium mb-1">{{ $document['title'] }}</p>
                                    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <span>{{ $document['category'] }}</span>
                                        <span>•</span>
                                        <span>{{ $document['assignee'] }}</span>
                                        <span>•</span>
                                        <span>{{ $document['updated_at'] }}</span>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <x-filament::badge :color="$document['status_color']" size="sm">
                                        {{ $document['status'] }}
                                    </x-filament::badge>
                                </div>
                            </div>
                        </x-filament::card>
                    @empty
                        <x-filament::card class="text-center py-8">
                            <div class="text-4xl mb-2">📄</div>
                            <p class="text-gray-500 dark:text-gray-400">No hay documentos recientes</p>
                        </x-filament::card>
                    @endforelse
                </div>
            </div>
        </div>
        
        <!-- Quick Action Buttons -->
        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-wrap gap-3">
                <x-filament::button 
                    tag="a" 
                    href="{{ route('filament.admin.resources.documents.create') }}"
                    color="primary"
                    icon="heroicon-o-plus">
                    Nuevo Documento
                </x-filament::button>
                
                <x-filament::button 
                    color="success"
                    icon="heroicon-o-magnifying-glass"
                    onclick="openQuickSearch()">
                    Búsqueda Rápida
                </x-filament::button>
                
                <x-filament::button 
                    tag="a" 
                    href="{{ route('filament.admin.resources.documents.index') }}"
                    color="info"
                    icon="heroicon-o-document-text">
                    Ver Todos los Documentos
                </x-filament::button>
                
                <x-filament::button 
                    color="warning"
                    icon="heroicon-o-chart-bar"
                    onclick="showNotification('info', 'Funcionalidad en desarrollo', 'Esta característica estará disponible pronto.')">
                    Reportes
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>