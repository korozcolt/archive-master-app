<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Aprobaciones Pendientes') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if($approvals->count() > 0)
                        <div class="mb-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Tienes <span class="font-semibold text-blue-600 dark:text-blue-400">{{ $approvals->total() }}</span> aprobaciones pendientes
                            </p>
                        </div>

                        <div class="space-y-4">
                            @foreach($approvals as $approval)
                                @php
                                    $document = $approval->document;
                                    $priorityColors = [
                                        'low' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                        'medium' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                                        'high' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
                                        'urgent' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                                    ];
                                    $priorityColor = $priorityColors[$document->priority->value ?? 'medium'];
                                @endphp

                                <div class="border dark:border-gray-700 rounded-lg p-6 hover:shadow-lg transition-shadow bg-white dark:bg-gray-800">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <!-- Título y número de documento -->
                                            <div class="flex items-center gap-3 mb-2">
                                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                                    {{ $document->title }}
                                                </h3>
                                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                                    #{{ $document->document_number }}
                                                </span>
                                            </div>

                                            <!-- Información del documento -->
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4 text-sm">
                                                <div>
                                                    <span class="text-gray-600 dark:text-gray-400">Categoría:</span>
                                                    <span class="font-medium text-gray-900 dark:text-gray-100">
                                                        {{ $document->category->name ?? 'Sin categoría' }}
                                                    </span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-600 dark:text-gray-400">Creado por:</span>
                                                    <span class="font-medium text-gray-900 dark:text-gray-100">
                                                        {{ $document->creator->name ?? 'Desconocido' }}
                                                    </span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-600 dark:text-gray-400">Estado actual:</span>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                                        {{ $document->status->name ?? 'Sin estado' }}
                                                    </span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-600 dark:text-gray-400">Fecha creación:</span>
                                                    <span class="font-medium text-gray-900 dark:text-gray-100">
                                                        {{ $document->created_at->format('d/m/Y H:i') }}
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Descripción -->
                                            @if($document->description)
                                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4 line-clamp-2">
                                                    {{ $document->description }}
                                                </p>
                                            @endif

                                            <!-- Workflow transition info -->
                                            @if($approval->workflowDefinition)
                                                <div class="flex items-center gap-2 mb-3">
                                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                                    </svg>
                                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                                        Transición:
                                                        <span class="font-medium">{{ $approval->workflowDefinition->fromStatus->name ?? '' }}</span>
                                                        →
                                                        <span class="font-medium">{{ $approval->workflowDefinition->toStatus->name ?? '' }}</span>
                                                    </span>
                                                    </span>
                                                </div>
                                            @endif

                                            <!-- Badges de prioridad y estado -->
                                            <div class="flex gap-2 flex-wrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $priorityColor }}">
                                                    {{ ucfirst($document->priority->getLabel()) }}
                                                </span>

                                                @if($document->is_confidential)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                                                        </svg>
                                                        Confidencial
                                                    </span>
                                                @endif

                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
                                                    ⏳ Pendiente de aprobación
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Acciones -->
                                        <div class="ml-6 flex flex-col gap-2">
                                            <a href="{{ route('approvals.show', $document->id) }}"
                                               class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                                Revisar
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <!-- Estado vacío -->
                        <div class="text-center py-12">
                            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-gray-100">No hay aprobaciones pendientes</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                No tienes documentos pendientes de aprobación en este momento.
                            </p>
                        </div>
                    @endif

                    @if($approvals->count() > 0)
                        <!-- Paginación -->
                        <div class="mt-6">
                            {{ $approvals->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
