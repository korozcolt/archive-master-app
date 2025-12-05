<x-filament-widgets::widget>
    <x-filament::section>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Pending Documents -->
            <div>
                <h3 class="text-lg font-semibold mb-4">📋 Mis Documentos Pendientes</h3>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    @forelse($pendingDocuments as $document)
                        <div class="p-4 rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-950/5 dark:ring-white/10">
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
                        </div>
                    @empty
                        <div class="text-center py-8 rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-950/5 dark:ring-white/10">
                            <div class="text-4xl mb-2">🎉</div>
                            <p class="text-gray-500 dark:text-gray-400">¡No tienes documentos pendientes!</p>
                        </div>
                    @endforelse
                </div>
            </div>
            
            <!-- Recent Documents -->
            <div>
                <h3 class="text-lg font-semibold mb-4">🕒 Documentos Recientes</h3>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    @forelse($recentDocuments as $document)
                        <div class="p-4 rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-950/5 dark:ring-white/10">
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
                        </div>
                    @empty
                        <div class="text-center py-8 rounded-lg bg-white dark:bg-gray-800 ring-1 ring-gray-950/5 dark:ring-white/10">
                            <div class="text-4xl mb-2">📄</div>
                            <p class="text-gray-500 dark:text-gray-400">No hay documentos recientes</p>
                        </div>
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