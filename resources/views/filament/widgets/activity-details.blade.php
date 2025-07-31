<div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">Información General</h4>
            <dl class="mt-2 space-y-1">
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Evento:</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100 capitalize">{{ $activity->event }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Usuario:</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $activity->causer?->name ?? 'Sistema' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Fecha:</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $activity->created_at->format('d/m/Y H:i:s') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500 dark:text-gray-400">IP:</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $activity->properties['ip'] ?? 'No disponible' }}</dd>
                </div>
            </dl>
        </div>
        
        <div>
            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">Elemento Afectado</h4>
            <dl class="mt-2 space-y-1">
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Tipo:</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100">
                        @switch($activity->subject_type)
                            @case('App\\Models\\Document')
                                Documento
                                @break
                            @case('App\\Models\\User')
                                Usuario
                                @break
                            @case('App\\Models\\Company')
                                Empresa
                                @break
                            @case('App\\Models\\Branch')
                                Sucursal
                                @break
                            @case('App\\Models\\Department')
                                Departamento
                                @break
                            @default
                                Desconocido
                        @endswitch
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500 dark:text-gray-400">ID:</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $activity->subject_id ?? 'N/A' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Nombre:</dt>
                    <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $activity->subject?->title ?? $activity->subject?->name ?? 'Elemento eliminado' }}</dd>
                </div>
            </dl>
        </div>
    </div>
    
    @if($activity->description)
        <div>
            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">Descripción</h4>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $activity->description }}</p>
        </div>
    @endif
    
    @if(!empty($properties))
        <div>
            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">Propiedades</h4>
            <div class="mt-2 space-y-3">
                @if(isset($properties['attributes']) && !empty($properties['attributes']))
                    <div>
                        <h5 class="text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wide">Valores Nuevos</h5>
                        <div class="mt-1 bg-green-50 dark:bg-green-900/20 rounded-md p-3">
                            <pre class="text-xs text-green-800 dark:text-green-200 whitespace-pre-wrap">{{ json_encode($properties['attributes'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    </div>
                @endif
                
                @if(isset($properties['old']) && !empty($properties['old']))
                    <div>
                        <h5 class="text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wide">Valores Anteriores</h5>
                        <div class="mt-1 bg-red-50 dark:bg-red-900/20 rounded-md p-3">
                            <pre class="text-xs text-red-800 dark:text-red-200 whitespace-pre-wrap">{{ json_encode($properties['old'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    </div>
                @endif
                
                @php
                    $otherProperties = collect($properties)->except(['attributes', 'old', 'ip'])->toArray();
                @endphp
                
                @if(!empty($otherProperties))
                    <div>
                        <h5 class="text-xs font-medium text-gray-700 dark:text-gray-200 uppercase tracking-wide">Información Adicional</h5>
                        <div class="mt-1 bg-gray-50 dark:bg-gray-800 rounded-md p-3">
                            <pre class="text-xs text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ json_encode($otherProperties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
    
    @if($activity->batch_uuid)
        <div>
            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">Información de Lote</h4>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $activity->batch_uuid }}</p>
        </div>
    @endif
</div>