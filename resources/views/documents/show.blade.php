@extends('layouts.app')

@section('title', $document->title)

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <div class="flex justify-between items-start">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ $document->title }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Código: {{ $document->document_number }}
                    </p>
                </div>
                <div class="flex space-x-2">
                    @if($document->created_by == Auth::id() || $document->assigned_to == Auth::id())
                        <a href="{{ route('documents.edit', $document) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                            Editar
                        </a>
                    @endif
                    <a href="{{ route('documents.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                        Volver
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Details -->
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Información del Documento
            </h3>

            <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Categoría</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $document->category->name ?? 'Sin categoría' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Estado</dt>
                    <dd class="mt-1">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                            @if($document->status->name == 'Completado') bg-green-100 text-green-800
                            @elseif($document->status->name == 'En Proceso') bg-yellow-100 text-yellow-800
                            @else bg-gray-100 text-gray-800
                            @endif">
                            {{ $document->status->name ?? 'Sin estado' }}
                        </span>
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Prioridad</dt>
                    <dd class="mt-1">
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                            @if($document->priority->value == 'high' || $document->priority->value == 'urgent') bg-red-100 text-red-800
                            @elseif($document->priority->value == 'medium') bg-yellow-100 text-yellow-800
                            @else bg-green-100 text-green-800
                            @endif">
                            {{ $document->priority->getLabel() }}
                        </span>
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Confidencial</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        @if($document->is_confidential)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                                </svg>
                                Sí
                            </span>
                        @else
                            <span class="text-gray-500">No</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Creado por</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $document->creator->name ?? 'Desconocido' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Asignado a</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $document->assignee->name ?? 'No asignado' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Fecha de creación</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $document->created_at->format('d/m/Y H:i') }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Última actualización</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                        {{ $document->updated_at->format('d/m/Y H:i') }}
                    </dd>
                </div>

                @if($document->description)
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Descripción</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                            {{ $document->description }}
                        </dd>
                    </div>
                @endif

                @if($document->file)
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Archivo adjunto</dt>
                        <dd class="mt-1">
                            <a href="{{ route('documents.download', $document) }}" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Descargar archivo
                            </a>
                        </dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

    @if($latestAiRun)
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Resumen Ejecutivo (IA)
                    </h3>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold
                            @if($latestAiRun->status === 'success') bg-green-100 text-green-700
                            @elseif($latestAiRun->status === 'running' || $latestAiRun->status === 'queued') bg-yellow-100 text-yellow-700
                            @elseif($latestAiRun->status === 'skipped') bg-gray-100 text-gray-700
                            @else bg-red-100 text-red-700 @endif">
                            {{ strtoupper($latestAiRun->status) }}
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ strtoupper($latestAiRun->provider) }} • {{ $latestAiRun->updated_at?->format('d/m/Y H:i') }}
                        </span>
                    </div>
                </div>

                @if($latestAiOutput && Auth::user()->can('view', $latestAiOutput))
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Resumen</p>
                        <div class="mt-2 text-sm leading-6 text-gray-700 dark:text-gray-300">
                            {!! nl2br(e($latestAiOutput->summary_md ?? 'Sin resumen disponible.')) !!}
                        </div>
                    </div>

                    @if(!empty($latestAiOutput->executive_bullets) && is_array($latestAiOutput->executive_bullets))
                        <div>
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Puntos clave</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-700 dark:text-gray-300">
                                @foreach($latestAiOutput->executive_bullets as $bullet)
                                    <li>{{ $bullet }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Sugerencias IA</p>
                        <div class="mt-2 space-y-2 text-sm text-gray-700 dark:text-gray-300">
                            <p>
                                <span class="font-medium">Tags sugeridos:</span>
                                {{ collect($latestAiOutput->suggested_tags ?? [])->join(', ') ?: 'Sin sugerencias' }}
                            </p>
                            <p>
                                <span class="font-medium">Categoría sugerida:</span>
                                {{ optional(\App\Models\Category::find($latestAiOutput->suggested_category_id))->name ?? 'Sin sugerencia' }}
                            </p>
                            <p>
                                <span class="font-medium">Departamento sugerido:</span>
                                {{ optional(\App\Models\Department::find($latestAiOutput->suggested_department_id))->name ?? 'Sin sugerencia' }}
                            </p>
                        </div>
                    </div>

                    @if(Auth::user()->can('viewEntities', $latestAiOutput))
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Entidades detectadas y confianza</p>
                            <div class="mt-2 space-y-2 text-sm text-gray-700 dark:text-gray-300">
                                <p>
                                    <span class="font-medium">Entidades:</span>
                                    {{ !empty($latestAiOutput->entities) ? json_encode($latestAiOutput->entities, JSON_UNESCAPED_UNICODE) : 'Sin entidades' }}
                                </p>
                                <p>
                                    <span class="font-medium">Confianza:</span>
                                    {{ !empty($latestAiOutput->confidence) ? json_encode($latestAiOutput->confidence, JSON_UNESCAPED_UNICODE) : 'Sin datos de confianza' }}
                                </p>
                            </div>
                        </div>
                    @endif
                @endif

                <div class="flex flex-wrap gap-2">
                    @if(Auth::user()->can('create', \App\Models\DocumentAiRun::class))
                        <form method="POST" action="{{ route('documents.ai.regenerate', $document) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">
                                Regenerar IA
                            </button>
                        </form>
                    @endif

                    @if($latestAiOutput && Auth::user()->can('applySuggestions', $latestAiOutput))
                        <form method="POST" action="{{ route('documents.ai.apply', $document) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-md bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">
                                Aplicar sugerencias
                            </button>
                        </form>
                    @endif

                    @if($latestAiOutput && Auth::user()->can('markIncorrect', $latestAiOutput))
                        <form method="POST" action="{{ route('documents.ai.mark-incorrect', $document) }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="text" name="feedback_note" maxlength="255" placeholder="¿Qué salió mal? (opcional)" class="rounded-md border-gray-300 px-3 py-2 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                            <button type="submit" class="inline-flex items-center rounded-md bg-amber-600 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-700">
                                Marcar como incorrecto
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Tags -->
    @if($document->tags->count() > 0)
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                    Etiquetas
                </h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($document->tags as $tag)
                        <span class="px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                            {{ $tag->name }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if($document->receipts->count() > 0)
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                    Recibidos
                </h3>
                <div class="space-y-3">
                    @foreach($document->receipts as $receipt)
                        <div class="flex items-center justify-between rounded border border-gray-200 p-3 dark:border-gray-700">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $receipt->receipt_number }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $receipt->recipient_name }} ({{ $receipt->recipient_email }})</p>
                            </div>
                            <a href="{{ route('receipts.show', $receipt) }}" class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">
                                Ver recibido
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- Delete Button (Solo para el creador) -->
    @if($document->created_by == Auth::id())
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-medium text-red-600 dark:text-red-400 mb-2">
                    Zona de Peligro
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Una vez eliminado, este documento no se puede recuperar.
                </p>
                <form action="{{ route('documents.destroy', $document) }}" method="POST" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este documento?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                        Eliminar Documento
                    </button>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
