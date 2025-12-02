<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Revisar Documento para Aprobación') }}
            </h2>
            <a href="{{ route('approvals.index') }}" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">
                ← Volver a mis aprobaciones
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Información del documento (2/3) -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div>
                                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                                        {{ $document->title }}
                                    </h1>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        #{{ $document->document_number }}
                                    </p>
                                </div>
                                @php
                                    $priorityColors = [
                                        'low' => 'bg-gray-100 text-gray-800',
                                        'medium' => 'bg-blue-100 text-blue-800',
                                        'high' => 'bg-orange-100 text-orange-800',
                                        'urgent' => 'bg-red-100 text-red-800',
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $priorityColors[$document->priority->value ?? 'medium'] }}">
                                    {{ ucfirst($document->priority->getLabel()) }}
                                </span>
                            </div>

                            @if($document->description)
                                <div class="mb-6">
                                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Descripción</h3>
                                    <p class="text-gray-600 dark:text-gray-400">{{ $document->description }}</p>
                                </div>
                            @endif

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Categoría</h4>
                                    <p class="text-gray-900 dark:text-gray-100">{{ $document->category->name ?? 'Sin categoría' }}</p>
                                </div>
                                <div>
                                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Estado</h4>
                                    <p class="text-gray-900 dark:text-gray-100">{{ $document->status->name ?? 'Sin estado' }}</p>
                                </div>
                                <div>
                                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Creado por</h4>
                                    <p class="text-gray-900 dark:text-gray-100">{{ $document->creator->name ?? 'Desconocido' }}</p>
                                </div>
                                <div>
                                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Fecha de creación</h4>
                                    <p class="text-gray-900 dark:text-gray-100">{{ $document->created_at->format('d/m/Y H:i') }}</p>
                                </div>
                                @if($document->due_at)
                                    <div>
                                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Fecha límite</h4>
                                        <p class="text-gray-900 dark:text-gray-100">{{ $document->due_at->format('d/m/Y') }}</p>
                                    </div>
                                @endif
                                @if($approval->workflowDefinition)
                                    <div class="md:col-span-2">
                                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-1">Transición de Estado</h4>
                                        <p class="text-gray-900 dark:text-gray-100">
                                            {{ $approval->workflowDefinition->fromStatus->name ?? 'Sin estado' }} 
                                            <span class="text-gray-500 mx-2">→</span> 
                                            {{ $approval->workflowDefinition->toStatus->name ?? 'Sin estado' }}
                                        </p>
                                    </div>
                                @endif
                            </div>

                            @if($document->file_path)
                                <div class="border-t dark:border-gray-700 pt-4">
                                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Archivo adjunto</h3>
                                    <a href="{{ Storage::url($document->file_path) }}" 
                                       target="_blank"
                                       class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 rounded-md transition-colors">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        Ver documento
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Panel de aprobación (1/3) -->
                <div class="lg:col-span-1">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg sticky top-6">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                                Aprobación Requerida
                            </h3>
                            
                            @if($approval->workflowDefinition)
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                    {{ $approval->workflowDefinition->name ?? 'Aprobación de transición' }}
                                </p>
                            @endif

                            <div class="space-y-4" x-data="{ action: null, comments: '' }">
                                <!-- Aprobar -->
                                <div>
                                    <button @click="action = 'approve'" 
                                            class="w-full inline-flex items-center justify-center px-4 py-3 bg-green-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-green-700 active:bg-green-900 focus:outline-none focus:border-green-900 focus:ring ring-green-300 disabled:opacity-25 transition ease-in-out duration-150">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Aprobar Documento
                                    </button>

                                    <div x-show="action === 'approve'" x-cloak class="mt-4">
                                        <form method="POST" action="{{ route('approvals.approve', $approval->id) }}">
                                            @csrf
                                            <div class="mb-4">
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                    Comentarios (opcional)
                                                </label>
                                                <textarea name="comments" 
                                                          rows="3" 
                                                          class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50"
                                                          placeholder="Agrega comentarios sobre tu aprobación..."></textarea>
                                            </div>
                                            <div class="flex gap-2">
                                                <button type="submit" 
                                                        class="flex-1 inline-flex items-center justify-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:outline-none focus:border-green-900 focus:ring ring-green-300 disabled:opacity-25 transition ease-in-out duration-150">
                                                    Confirmar Aprobación
                                                </button>
                                                <button type="button" 
                                                        @click="action = null"
                                                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                                                    Cancelar
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Rechazar -->
                                <div>
                                    <button @click="action = 'reject'" 
                                            class="w-full inline-flex items-center justify-center px-4 py-3 bg-red-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-red-700 active:bg-red-900 focus:outline-none focus:border-red-900 focus:ring ring-red-300 disabled:opacity-25 transition ease-in-out duration-150">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                        Rechazar Documento
                                    </button>

                                    <div x-show="action === 'reject'" x-cloak class="mt-4">
                                        <form method="POST" action="{{ route('approvals.reject', $approval->id) }}">
                                            @csrf
                                            <div class="mb-4">
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                    Motivo del rechazo <span class="text-red-500">*</span>
                                                </label>
                                                <textarea name="comments" 
                                                          rows="4" 
                                                          required
                                                          class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm focus:border-red-500 focus:ring focus:ring-red-200 focus:ring-opacity-50"
                                                          placeholder="Explica el motivo del rechazo..."></textarea>
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    El motivo es obligatorio para rechazar un documento
                                                </p>
                                            </div>
                                            <div class="flex gap-2">
                                                <button type="submit" 
                                                        class="flex-1 inline-flex items-center justify-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:outline-none focus:border-red-900 focus:ring ring-red-300 disabled:opacity-25 transition ease-in-out duration-150">
                                                    Confirmar Rechazo
                                                </button>
                                                <button type="button" 
                                                        @click="action = null"
                                                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                                                    Cancelar
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <div class="pt-4 border-t dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Una vez que apruebes o rechaces este documento, no podrás modificar tu decisión.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>
</x-app-layout>
