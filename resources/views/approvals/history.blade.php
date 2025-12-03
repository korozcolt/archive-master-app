<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Historial de Aprobaciones') }}
            </h2>
            <a href="{{ route('documents.show', $document) }}" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">
                ← Volver al documento
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Información del documento -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                        {{ $document->title }}
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        #{{ $document->document_number }} | Estado actual: <span class="font-medium">{{ $document->status->name ?? 'Sin estado' }}</span>
                    </p>
                </div>
            </div>

            <!-- Historial de aprobaciones -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        Historial de Aprobaciones
                    </h3>

                    @if($approvals->count() > 0)
                        <div class="space-y-4">
                            @foreach($approvals as $approval)
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                        'approved' => 'bg-green-100 text-green-800 border-green-200',
                                        'rejected' => 'bg-red-100 text-red-800 border-red-200',
                                    ];
                                    $statusColor = $statusColors[$approval->status] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                                @endphp

                                <div class="border dark:border-gray-700 rounded-lg p-4 {{ $statusColor }}">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-3 mb-2">
                                                <h4 class="font-semibold text-gray-900 dark:text-gray-100">
                                                    {{ $approval->approver->name ?? 'Desconocido' }}
                                                </h4>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                                    {{ ucfirst($approval->status) }}
                                                </span>
                                            </div>

                                            @if($approval->workflowDefinition)
                                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                                    <strong>Transición:</strong>
                                                    {{ $approval->workflowDefinition->fromStatus->name ?? '' }}
                                                    →
                                                    {{ $approval->workflowDefinition->toStatus->name ?? '' }}
                                                </p>
                                            @endif

                                            @if($approval->comments)
                                                <div class="mt-2 p-3 bg-white dark:bg-gray-700 rounded border border-gray-200 dark:border-gray-600">
                                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                                        <strong>Comentarios:</strong> {{ $approval->comments }}
                                                    </p>
                                                </div>
                                            @endif

                                            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                                <span>Solicitado: {{ $approval->created_at->format('d/m/Y H:i') }}</span>
                                                @if($approval->responded_at)
                                                    <span class="ml-4">Respondido: {{ $approval->responded_at->format('d/m/Y H:i') }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- Ícono de estado -->
                                        <div class="ml-4">
                                            @if($approval->status === 'approved')
                                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            @elseif($approval->status === 'rejected')
                                                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            @else
                                                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-gray-100">No hay historial de aprobaciones</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Este documento aún no tiene aprobaciones registradas.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
