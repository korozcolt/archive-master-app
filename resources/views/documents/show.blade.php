@extends('layouts.app')

@section('title', $document->title)

@section('content')
@php
    $translateName = function ($model, string $fallback = 'Sin dato') {
        if ($model && method_exists($model, 'getTranslation')) {
            $value = $model->getTranslation('name', app()->getLocale(), false);

            if (is_string($value) && str_starts_with($value, '{')) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    return (string) ($decoded[app()->getLocale()] ?? $decoded['es'] ?? $decoded['en'] ?? reset($decoded) ?? $fallback);
                }
            }

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $raw = data_get($model, 'name');

        if (is_string($raw) && str_starts_with($raw, '{')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return (string) ($decoded[app()->getLocale()] ?? $decoded['es'] ?? $decoded['en'] ?? reset($decoded) ?? $fallback);
            }
        }

        return (string) ($raw ?: $fallback);
    };

    $statusLabel = $translateName($document->status, 'Sin estado');
    $categoryLabel = $translateName($document->category, 'Sin categoría');
    $statusDotClass = str_contains(mb_strtolower($statusLabel), 'aprob') ? 'bg-emerald-500' : (str_contains(mb_strtolower($statusLabel), 'proceso') ? 'bg-amber-500' : 'bg-slate-400');
    $hasPreview = filled($document->file_path);
    $latestVersion = $document->versions->sortByDesc('id')->first();
    $documentFileExtension = \App\Support\FileExtensionIcon::extensionFromPath($document->file_path);
    $previewableExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
    $canInlinePreview = $hasPreview && in_array($documentFileExtension, $previewableExtensions, true);
@endphp

<div class="space-y-6">
    <section class="rounded-2xl border border-white/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0">
                <div class="mb-2 flex flex-wrap items-center gap-2 text-sm">
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <span class="h-1.5 w-1.5 rounded-full {{ $statusDotClass }}"></span>
                        {{ $statusLabel }}
                    </span>
                    <span class="text-slate-500 dark:text-slate-400">ID: #{{ $document->id }}</span>
                    <span class="text-slate-300 dark:text-slate-600">•</span>
                    <span class="text-slate-500 dark:text-slate-400">{{ $document->document_number ?: 'Sin número de documento' }}</span>
                </div>
                <h1 class="truncate text-2xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-3xl">{{ $document->title }}</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600 dark:text-slate-400">
                    {{ $document->description ?: 'Consulta la información, previsualización, insights de IA y trazabilidad completa del documento.' }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($document->created_by == Auth::id() || $document->assigned_to == Auth::id())
                    <a href="{{ route('documents.edit', $document) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                        Editar
                    </a>
                @endif
                @if($hasPreview)
                    <a href="{{ route('documents.download', $document) }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-sky-300/20 bg-gradient-to-r from-sky-500 to-indigo-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:from-sky-400 hover:to-indigo-500">
                        Descargar
                    </a>
                @endif
                <a href="{{ route('documents.index') }}" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                    Volver
                </a>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 2xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="overflow-hidden rounded-2xl border border-white/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-slate-600 dark:text-slate-300">Previsualización</h2>
                    @if($latestVersion)
                        <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">v{{ $latestVersion->version_number ?? $latestVersion->id }}</span>
                    @endif
                </div>
                @if($hasPreview)
                    <a href="{{ $canInlinePreview ? route('documents.preview', $document) : route('documents.download', $document) }}" target="_blank" class="text-sm font-medium text-sky-700 hover:text-sky-800 dark:text-sky-300">
                        {{ $canInlinePreview ? 'Abrir/Descargar' : 'Descargar' }}
                    </a>
                @endif
            </div>

            <div class="bg-slate-200/70 p-4 dark:bg-slate-950/60 sm:p-6">
                @if($canInlinePreview)
                    <div class="mx-auto flex min-h-[640px] max-w-4xl items-center justify-center rounded-lg bg-white shadow-2xl ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                        <iframe
                            src="{{ route('documents.preview', $document) }}"
                            class="h-[70vh] min-h-[620px] w-full rounded-lg"
                            title="Vista previa del documento"
                        ></iframe>
                    </div>
                @elseif($hasPreview)
                    <div class="mx-auto flex min-h-[500px] max-w-3xl items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 text-center dark:border-slate-700 dark:bg-slate-800/40">
                        <div class="px-6">
                            <div class="mx-auto mb-3 flex justify-center">
                                <x-file-extension-icon :extension="$documentFileExtension" class="h-14 w-14" size="h-7 w-7" />
                            </div>
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Previsualización no disponible para este tipo de archivo</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Puedes abrirlo o descargarlo para verlo en una aplicación compatible.</p>
                            <a href="{{ route('documents.download', $document) }}" class="mt-4 inline-flex h-10 items-center justify-center rounded-xl border border-sky-300/20 bg-gradient-to-r from-sky-500 to-indigo-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:from-sky-400 hover:to-indigo-500">
                                Abrir / Descargar archivo
                            </a>
                        </div>
                    </div>
                @else
                    <div class="mx-auto flex min-h-[500px] max-w-3xl items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 text-center dark:border-slate-700 dark:bg-slate-800/40">
                        <div class="px-6">
                            <div class="mx-auto mb-2 flex justify-center">
                                <x-file-extension-icon :extension="$documentFileExtension" class="h-14 w-14" size="h-7 w-7" />
                            </div>
                            <p class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Sin archivo adjunto</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Este documento no tiene archivo digital disponible para previsualización.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <aside class="overflow-hidden rounded-2xl border border-white/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-4 py-4 dark:border-slate-800">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <x-file-extension-icon :extension="$documentFileExtension" class="h-10 w-10" size="h-5 w-5" />
                    <div class="flex gap-1">
                        @if($hasPreview)
                            <a href="{{ route('documents.download', $document) }}" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200">⬇️</a>
                        @endif
                    </div>
                </div>
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ Str::limit($document->title, 50) }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $document->document_number ?: 'Sin número' }}</p>
            </div>

            <div class="max-h-[850px] space-y-6 overflow-y-auto p-4">
                <div class="space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Propiedades</h3>
                    <div class="grid grid-cols-[110px_1fr] gap-y-3 gap-x-3 text-sm">
                        <span class="text-slate-500 dark:text-slate-400">Categoría</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $categoryLabel }}</span>
                        <span class="text-slate-500 dark:text-slate-400">Estado</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $statusLabel }}</span>
                        <span class="text-slate-500 dark:text-slate-400">Prioridad</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $document->priority?->getLabel() }}</span>
                        <span class="text-slate-500 dark:text-slate-400">Creado por</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $document->creator?->name ?? 'Desconocido' }}</span>
                        <span class="text-slate-500 dark:text-slate-400">Asignado a</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $document->assignee?->name ?? 'No asignado' }}</span>
                        <span class="text-slate-500 dark:text-slate-400">Creado</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $document->created_at?->format('d/m/Y H:i') }}</span>
                        <span class="text-slate-500 dark:text-slate-400">Actualizado</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $document->updated_at?->format('d/m/Y H:i') }}</span>
                        <span class="text-slate-500 dark:text-slate-400">Confidencial</span>
                        <span class="font-medium {{ $document->is_confidential ? 'text-rose-600' : 'text-slate-900 dark:text-white' }}">{{ $document->is_confidential ? 'Sí' : 'No' }}</span>
                    </div>
                </div>

                @if(Auth::user()?->hasRole(\App\Enums\Role::ArchiveManager->value))
                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Archivo físico</h3>
                            <a href="{{ route('stickers.documents.download', ['document' => $document->id]) }}"
                               class="inline-flex h-8 items-center justify-center rounded-lg border border-indigo-300/20 bg-gradient-to-r from-sky-500 to-indigo-600 px-3 text-xs font-semibold text-white shadow-sm hover:from-sky-400 hover:to-indigo-500"
                               target="_blank">
                                Imprimir etiqueta
                            </a>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/40">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Ubicación actual</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                                        {{ $document->physicalLocation?->full_path ?: 'Sin ubicación asignada' }}
                                    </p>
                                    @if($document->physicalLocation?->code)
                                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $document->physicalLocation->code }}</p>
                                    @endif
                                </div>
                                @if($document->physicalLocation)
                                    <a href="{{ route('stickers.locations.download', ['location' => $document->physicalLocation->id]) }}"
                                       class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                       target="_blank">
                                        Etiqueta ubicación
                                    </a>
                                @endif
                            </div>
                        </div>

                        <form method="POST" action="{{ route('documents.archive-location.update', $document) }}" class="space-y-3 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                            @csrf
                            <div>
                                <label for="physical_location_id" class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Asignar ubicación</label>
                                <select id="physical_location_id"
                                        name="physical_location_id"
                                        class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                    <option value="">Seleccionar ubicación</option>
                                    @foreach(($archiveLocationOptions ?? collect()) as $locationOption)
                                        <option value="{{ $locationOption->id }}"
                                            @selected((int) old('physical_location_id', $document->physical_location_id) === (int) $locationOption->id)>
                                            {{ $locationOption->full_path }} ({{ $locationOption->code }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('physical_location_id')
                                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="archive_note" class="mb-1.5 block text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Nota de movimiento (opcional)</label>
                                <textarea id="archive_note"
                                          name="archive_note"
                                          rows="2"
                                          class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                          placeholder="Ej. Ingreso a estante B / caja 04">{{ old('archive_note') }}</textarea>
                            </div>

                            <button type="submit"
                                    class="inline-flex h-10 w-full items-center justify-center rounded-xl bg-gradient-to-r from-sky-500 to-indigo-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:from-sky-400 hover:to-indigo-500">
                                {{ $document->physicalLocation ? 'Mover / actualizar ubicación' : 'Asignar ubicación en archivo' }}
                            </button>
                        </form>

                        @if(($documentLocationHistory ?? collect())->isNotEmpty())
                            <div class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Movimientos recientes de ubicación</p>
                                <div class="space-y-2">
                                    @foreach($documentLocationHistory as $locationMove)
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-800/60">
                                            <div class="font-medium text-slate-800 dark:text-slate-100">
                                                {{ $locationMove->getMovementDescription() }}
                                            </div>
                                            <div class="mt-0.5 text-slate-500 dark:text-slate-400">
                                                {{ $locationMove->moved_at?->format('d/m/Y H:i') }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @if($document->tags->count() > 0)
                    <div class="space-y-3">
                        <h3 class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Etiquetas</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach($document->tags as $tag)
                                <span class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700 dark:border-sky-900/40 dark:bg-sky-900/20 dark:text-sky-300">
                                    {{ $translateName($tag, 'Etiqueta') }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($document->receipts->count() > 0)
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Recibidos</h3>
                        </div>
                        <div class="space-y-2">
                            @foreach($document->receipts as $receipt)
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/40">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $receipt->receipt_number }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $receipt->recipient_name }} • {{ $receipt->recipient_email }}</p>
                                    <a href="{{ route('receipts.show', $receipt) }}" class="mt-2 inline-flex text-xs font-medium text-sky-700 hover:text-sky-800 dark:text-sky-300">Ver recibido</a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </aside>
    </section>

    <section x-data="{ aiInsightsOpen: false }" class="overflow-hidden rounded-2xl border border-white/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center justify-between gap-3 bg-gradient-to-r from-indigo-600 to-sky-600 px-4 py-3 text-white">
            <button type="button"
                    @click="aiInsightsOpen = !aiInsightsOpen"
                    class="inline-flex items-center gap-2 rounded-lg px-2 py-1 text-left text-sm font-semibold uppercase tracking-[0.14em] hover:bg-white/10">
                <span class="text-base">✨</span>
                <span>Insights de IA</span>
                <span class="rounded-md bg-white/10 px-2 py-0.5 text-[10px] font-bold tracking-[0.14em]" x-text="aiInsightsOpen ? 'Ocultar' : 'Mostrar'"></span>
            </button>
            <div class="flex items-center gap-2">
                @if($latestAiRun)
                    <span class="inline-flex items-center rounded-md bg-white/10 px-2 py-1 text-[11px] font-semibold">
                        {{ strtoupper($latestAiRun->status) }}
                    </span>
                @endif
                @if(Auth::user()->can('create', \App\Models\DocumentAiRun::class))
                    <form method="POST" action="{{ route('documents.ai.regenerate', $document) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-white/10 px-2 py-1 text-xs font-semibold hover:bg-white/20">Regenerar</button>
                    </form>
                @endif
            </div>
        </div>

        <div x-cloak x-show="aiInsightsOpen" class="space-y-6 p-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/40">
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Resumen ejecutivo</h3>
                        @if($latestAiRun)
                            <span class="inline-flex items-center rounded-md px-2 py-1 text-[11px] font-semibold
                                @if($latestAiRun->status === 'success') bg-emerald-50 text-emerald-700
                                @elseif(in_array($latestAiRun->status, ['queued','running'])) bg-amber-50 text-amber-700
                                @elseif($latestAiRun->status === 'skipped') bg-slate-100 text-slate-700
                                @else bg-rose-50 text-rose-700 @endif">
                                {{ strtoupper($latestAiRun->status) }}
                            </span>
                        @endif
                    </div>
                    <div class="text-sm leading-6 text-slate-700 dark:text-slate-300">
                        @if($latestAiOutput && Auth::user()->can('view', $latestAiOutput))
                            {!! nl2br(e($latestAiOutput->summary_md ?: 'Sin resumen disponible.')) !!}
                        @else
                            <p>No hay salida de IA disponible para este documento.</p>
                        @endif
                    </div>
                </div>

                @if($latestAiOutput && Auth::user()->can('view', $latestAiOutput) && is_array($latestAiOutput->executive_bullets) && count($latestAiOutput->executive_bullets))
                    <div class="space-y-2">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Puntos clave</h3>
                        <ul class="space-y-2 text-sm text-slate-700 dark:text-slate-300">
                            @foreach($latestAiOutput->executive_bullets as $bullet)
                                <li class="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-800">• {{ $bullet }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Sugerencias</h3>
                    <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm dark:border-slate-700 dark:bg-slate-800">
                        @if($latestAiOutput && Auth::user()->can('view', $latestAiOutput))
                            <p class="mb-2 text-slate-700 dark:text-slate-300">
                                <span class="font-medium">Tags:</span>
                                {{ collect($latestAiOutput->suggested_tags ?? [])->join(', ') ?: 'Sin sugerencias' }}
                            </p>
                            <p class="mb-2 text-slate-700 dark:text-slate-300">
                                <span class="font-medium">Categoría sugerida:</span>
                                {{ $latestAiOutput->suggested_category_id ? (optional(\App\Models\Category::find($latestAiOutput->suggested_category_id))->getTranslation('name', app()->getLocale(), false) ?? 'Sin sugerencia') : 'Sin sugerencia' }}
                            </p>
                            <p class="text-slate-700 dark:text-slate-300">
                                <span class="font-medium">Departamento sugerido:</span>
                                {{ $latestAiOutput->suggested_department_id ? (optional(\App\Models\Department::find($latestAiOutput->suggested_department_id))->name ?? 'Sin sugerencia') : 'Sin sugerencia' }}
                            </p>
                        @else
                            <p class="text-slate-600 dark:text-slate-400">No hay sugerencias generadas para este documento.</p>
                        @endif
                    </div>

                    @if($latestAiOutput)
                        <div class="flex flex-wrap gap-2">
                            @if(Auth::user()->can('applySuggestions', $latestAiOutput))
                                <form method="POST" action="{{ route('documents.ai.apply', $document) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-emerald-600 px-3 text-xs font-semibold text-white hover:bg-emerald-700">
                                        Aplicar sugerencias
                                    </button>
                                </form>
                            @endif

                            @if(Auth::user()->can('markIncorrect', $latestAiOutput))
                                <form method="POST" action="{{ route('documents.ai.mark-incorrect', $document) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    <input type="text" name="feedback_note" maxlength="255" placeholder="Feedback (opcional)"
                                           class="h-9 rounded-lg border border-slate-300 bg-white px-3 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-amber-600 px-3 text-xs font-semibold text-white hover:bg-amber-700">
                                        Marcar incorrecto
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
    </section>

    <section class="rounded-2xl border border-white/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @php
            $distributionStatusLabel = fn (?string $status) => match ($status) {
                'sent' => 'Enviado',
                'received' => 'Recibido',
                'in_review' => 'En revisión',
                'responded' => 'Respondido',
                'rejected' => 'Rechazado',
                'closed' => 'Cerrado',
                default => 'Sin estado',
            };

            $distributionStatusClass = fn (?string $status) => match ($status) {
                'sent' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/40 dark:bg-sky-900/20 dark:text-sky-300',
                'received' => 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-900/40 dark:bg-indigo-900/20 dark:text-indigo-300',
                'in_review' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-900/20 dark:text-amber-300',
                'responded' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-300',
                'rejected' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/40 dark:bg-rose-900/20 dark:text-rose-300',
                'closed' => 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200',
                default => 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200',
            };
        @endphp

        <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">Distribución y Seguimiento</h2>
                <p class="text-sm text-slate-600 dark:text-slate-400">Envío del documento a oficinas y seguimiento independiente por destinatario.</p>
            </div>
        </div>

        @if((Auth::user()?->hasRole(\App\Enums\Role::Receptionist->value) || Auth::user()?->hasAnyRole(['admin', 'super_admin', 'branch_admin'])) && isset($distributionDepartmentOptions))
            <form method="POST"
                  action="{{ route('documents.distributions.store', $document) }}"
                  class="mb-6 rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm dark:border-slate-700 dark:from-slate-900 dark:to-slate-900"
                  x-data="{ selectedDepartments: [] }">
                @csrf
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1.5fr_1fr]">
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">
                                Oficinas destino <span class="text-rose-500">*</span>
                            </label>
                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                <span x-text="selectedDepartments.length"></span>&nbsp;seleccionada(s)
                            </span>
                        </div>

                        <div class="grid max-h-64 grid-cols-1 gap-2 overflow-y-auto rounded-xl border border-slate-200 bg-white p-3 sm:grid-cols-2 dark:border-slate-700 dark:bg-slate-900">
                            @foreach($distributionDepartmentOptions as $departmentOption)
                                @php
                                    $departmentLabel = $translateName($departmentOption, 'Oficina');
                                    $alreadyDistributed = (bool) data_get($departmentOption, 'already_distributed', false);
                                @endphp
                                <label class="group relative rounded-lg border p-3 transition {{ $alreadyDistributed ? 'cursor-not-allowed border-slate-200/80 bg-slate-100/80 opacity-70 dark:border-slate-700 dark:bg-slate-800/30' : 'cursor-pointer border-slate-200 bg-slate-50/80 hover:border-sky-300 hover:bg-sky-50 dark:border-slate-700 dark:bg-slate-800/40 dark:hover:border-sky-700 dark:hover:bg-sky-900/10' }}">
                                    <input type="checkbox"
                                           name="department_ids[]"
                                           value="{{ $departmentOption->id }}"
                                           class="peer sr-only"
                                           x-model="selectedDepartments"
                                           @disabled($alreadyDistributed)>
                                    <div class="flex items-start gap-3">
                                        <div class="mt-0.5 flex h-5 w-5 items-center justify-center rounded border border-slate-300 bg-white text-slate-400 transition peer-checked:border-sky-500 peer-checked:bg-sky-500 peer-checked:text-white dark:border-slate-600 dark:bg-slate-900 dark:text-slate-500">
                                            <svg x-show="selectedDepartments.includes('{{ (string) $departmentOption->id }}')" x-cloak class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.2 7.2a1 1 0 01-1.415.005L3.29 9.205a1 1 0 111.42-1.41l4.088 4.116 6.492-6.62a1 1 0 011.414-.001z" clip-rule="evenodd"/>
                                            </svg>
                                            <svg x-show="!selectedDepartments.includes('{{ (string) $departmentOption->id }}')" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" aria-hidden="true">
                                                <path d="M6 6l8 8M14 6l-8 8" stroke-width="1.75" stroke-linecap="round" />
                                            </svg>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <p class="text-sm font-semibold text-slate-900 peer-checked:text-sky-700 dark:text-white dark:peer-checked:text-sky-300">
                                                    {{ $departmentLabel }}
                                                </p>
                                                @if($alreadyDistributed)
                                                    <span class="inline-flex rounded-full border border-slate-300 bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300">
                                                        Ya enviada
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                                {{ $alreadyDistributed ? 'Esta oficina ya tiene un destino creado para este documento.' : 'Destino de distribución y seguimiento independiente.' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="pointer-events-none absolute inset-0 rounded-lg ring-2 ring-transparent transition {{ $alreadyDistributed ? '' : 'peer-checked:ring-sky-400/40' }}"></div>
                                </label>
                            @endforeach
                        </div>

                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Selecciona una o varias oficinas. Cada oficina tendrá seguimiento y estado independiente.
                        </p>
                        @error('department_ids')
                            <p class="text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-rows-[1fr_auto] gap-4">
                        <label for="routing_note" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Nota de envío</label>
                        <textarea id="routing_note" name="routing_note" rows="4"
                                  class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                  placeholder="Instrucciones para las oficinas destinatarias...">{{ old('routing_note') }}</textarea>
                        <div class="rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                            <div class="mb-3 space-y-1">
                                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Resumen del envío</p>
                                <p class="text-sm text-slate-700 dark:text-slate-300">
                                    Se crearán destinos individuales para cada oficina seleccionada.
                                </p>
                            </div>
                            <button type="submit"
                                    class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-gradient-to-r from-sky-500 to-indigo-600 px-4 text-sm font-semibold text-white shadow-lg shadow-indigo-900/20 transition hover:from-sky-400 hover:to-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="selectedDepartments.length === 0">
                                Enviar a oficinas
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        @endif

        @if(($distributionTargets ?? collect())->isEmpty())
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-400">
                Este documento aún no ha sido distribuido a oficinas.
            </div>
        @else
            @php
                $distributionSummary = [
                    'sent' => ($distributionTargets ?? collect())->where('status', 'sent')->count(),
                    'received' => ($distributionTargets ?? collect())->where('status', 'received')->count(),
                    'in_review' => ($distributionTargets ?? collect())->where('status', 'in_review')->count(),
                    'responded' => ($distributionTargets ?? collect())->where('status', 'responded')->count(),
                    'rejected' => ($distributionTargets ?? collect())->where('status', 'rejected')->count(),
                    'closed' => ($distributionTargets ?? collect())->where('status', 'closed')->count(),
                ];
            @endphp

            <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-6">
                @foreach([
                    ['key' => 'sent', 'label' => 'Enviadas', 'class' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/40 dark:bg-sky-900/20 dark:text-sky-300'],
                    ['key' => 'received', 'label' => 'Recibidas', 'class' => 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-900/40 dark:bg-indigo-900/20 dark:text-indigo-300'],
                    ['key' => 'in_review', 'label' => 'En revisión', 'class' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-900/20 dark:text-amber-300'],
                    ['key' => 'responded', 'label' => 'Respondidas', 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-300'],
                    ['key' => 'rejected', 'label' => 'Rechazadas', 'class' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/40 dark:bg-rose-900/20 dark:text-rose-300'],
                    ['key' => 'closed', 'label' => 'Cerradas', 'class' => 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200'],
                ] as $summaryCard)
                    <div class="rounded-xl border p-3 {{ $summaryCard['class'] }}">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em]">{{ $summaryCard['label'] }}</p>
                        <p class="mt-1 text-2xl font-black">{{ $distributionSummary[$summaryCard['key']] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50/70 dark:bg-slate-950/60">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">
                            <th class="px-3 py-3">Oficina</th>
                            <th class="px-3 py-3">Estado</th>
                            <th class="px-3 py-3">Última actividad</th>
                            <th class="px-3 py-3">Seguimiento / Respuesta</th>
                            <th class="px-3 py-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($distributionTargets as $target)
                            @php
                                $canUpdateTarget = Auth::user()?->hasAnyRole(['admin', 'super_admin', 'branch_admin'])
                                    || (Auth::user()?->hasRole(\App\Enums\Role::OfficeManager->value) && (int) Auth::user()->department_id === (int) $target->department_id);
                            @endphp
                            <tr class="bg-white align-top dark:bg-slate-900">
                                <td class="px-3 py-4 align-top">
                                    <div class="font-medium text-slate-900 dark:text-white">{{ $translateName($target->department, 'Oficina') }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        Enviado por {{ $target->distribution->creator?->name ?? 'Sistema' }}
                                        @if($target->sent_at)
                                            • {{ $target->sent_at->format('d/m/Y H:i') }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-4 align-top">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $distributionStatusClass($target->status) }}">
                                        {{ $distributionStatusLabel($target->status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 align-top text-xs text-slate-600 dark:text-slate-300">
                                    @if($target->last_activity_at)
                                        <div>{{ $target->last_activity_at->format('d/m/Y H:i') }}</div>
                                        <div class="mt-1 text-slate-500 dark:text-slate-400">por {{ $target->lastUpdatedBy?->name ?? 'Sistema' }}</div>
                                    @else
                                        <span class="text-slate-400">Sin actividad</span>
                                    @endif
                                </td>
                                <td class="px-3 py-4 align-top">
                                    <div class="space-y-2 text-xs">
                                        @if($target->routing_note)
                                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                                <div class="mb-1 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Nota de envío</div>
                                                <div>{{ $target->routing_note }}</div>
                                            </div>
                                        @endif
                                        @if($target->follow_up_note)
                                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-2 text-amber-800 dark:border-amber-900/40 dark:bg-amber-900/20 dark:text-amber-200">
                                                <div class="mb-1 text-[10px] font-bold uppercase tracking-[0.14em]">Seguimiento</div>
                                                <div class="prose prose-sm max-w-none dark:prose-invert">{!! $target->follow_up_note !!}</div>
                                            </div>
                                        @endif
                                        @if($target->response_note)
                                            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-2 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-200">
                                                <div class="mb-1 text-[10px] font-bold uppercase tracking-[0.14em]">Respuesta</div>
                                                <div class="prose prose-sm max-w-none dark:prose-invert">{!! $target->response_note !!}</div>
                                            </div>
                                        @endif
                                        @if($target->rejected_reason)
                                            <div class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-2 text-rose-800 dark:border-rose-900/40 dark:bg-rose-900/20 dark:text-rose-200">
                                                <div class="mb-1 text-[10px] font-bold uppercase tracking-[0.14em]">Motivo de rechazo</div>
                                                <div class="prose prose-sm max-w-none dark:prose-invert">{!! $target->rejected_reason !!}</div>
                                            </div>
                                        @endif
                                        @if($target->responseDocument)
                                            <div class="rounded-lg border border-sky-200 bg-sky-50 px-2.5 py-2 text-sky-800 dark:border-sky-900/40 dark:bg-sky-900/20 dark:text-sky-200">
                                                <div class="mb-1 text-[10px] font-bold uppercase tracking-[0.14em]">Documento de respuesta</div>
                                                <a href="{{ route('documents.show', $target->responseDocument) }}" class="font-semibold underline underline-offset-2">
                                                    {{ $target->responseDocument->title }}
                                                </a>
                                                @if($target->responseDocument->document_number)
                                                    <div class="mt-1 opacity-90">{{ $target->responseDocument->document_number }}</div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-4 align-top">
                                    @if($canUpdateTarget)
                                        <form method="POST"
                                              action="{{ route('documents.distribution-targets.update', [$document, $target]) }}"
                                              class="space-y-2"
                                              enctype="multipart/form-data"
                                              x-data="{
                                                actionType: 'received',
                                                htmlNote: '',
                                                syncNote() {
                                                    this.$refs.noteInput.value = this.htmlNote;
                                                },
                                                apply(command) {
                                                    document.execCommand(command, false, null);
                                                    this.htmlNote = this.$refs.editor.innerHTML;
                                                    this.syncNote();
                                                },
                                                applyLink() {
                                                    const url = window.prompt('URL del enlace');
                                                    if (!url) return;
                                                    document.execCommand('createLink', false, url);
                                                    this.htmlNote = this.$refs.editor.innerHTML;
                                                    this.syncNote();
                                                }
                                              }"
                                              @submit="htmlNote = $refs.editor.innerHTML; syncNote()">
                                            @csrf
                                            <select name="action"
                                                    x-model="actionType"
                                                    class="block h-9 w-full rounded-lg border border-slate-300 bg-white px-2 text-xs text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                                <option value="received">Marcar recibido</option>
                                                <option value="in_review">Marcar en revisión</option>
                                                <option value="respond_comment">Responder con comentario</option>
                                                <option value="respond_document">Responder con documento</option>
                                                <option value="reject">Rechazar (no corresponde)</option>
                                                <option value="close">Cerrar</option>
                                            </select>
                                            <div class="rounded-lg border border-slate-300 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                                <div class="flex flex-wrap gap-1 border-b border-slate-200 p-2 dark:border-slate-700">
                                                    <button type="button" @click="apply('bold')" class="rounded border border-slate-200 px-2 py-1 text-xs dark:border-slate-600">B</button>
                                                    <button type="button" @click="apply('italic')" class="rounded border border-slate-200 px-2 py-1 text-xs italic dark:border-slate-600">I</button>
                                                    <button type="button" @click="apply('underline')" class="rounded border border-slate-200 px-2 py-1 text-xs underline dark:border-slate-600">U</button>
                                                    <button type="button" @click="apply('justifyLeft')" class="rounded border border-slate-200 px-2 py-1 text-xs dark:border-slate-600">Izq</button>
                                                    <button type="button" @click="apply('justifyCenter')" class="rounded border border-slate-200 px-2 py-1 text-xs dark:border-slate-600">Centro</button>
                                                    <button type="button" @click="apply('insertUnorderedList')" class="rounded border border-slate-200 px-2 py-1 text-xs dark:border-slate-600">Lista</button>
                                                    <button type="button" @click="applyLink()" class="rounded border border-slate-200 px-2 py-1 text-xs dark:border-slate-600">Enlace</button>
                                                </div>
                                                <div x-ref="editor"
                                                     contenteditable="true"
                                                     @input="htmlNote = $refs.editor.innerHTML; syncNote()"
                                                     class="min-h-32 w-full rounded-b-lg px-3 py-2 text-sm text-slate-900 outline-none focus:ring-2 focus:ring-sky-400/20 dark:text-white"
                                                     style="white-space: pre-wrap;"
                                                     data-placeholder="Comentario, motivo de rechazo o respuesta (con formato)..."></div>
                                                <input type="hidden" name="note" x-ref="noteInput" value="">
                                            </div>
                                            <div x-show="actionType === 'respond_document'" x-cloak class="space-y-2">
                                                <input type="text" name="response_document_title"
                                                       class="block h-9 w-full rounded-lg border border-slate-300 bg-white px-2 text-xs text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                                       placeholder="Título del documento de respuesta (si vas a subir archivo)">
                                                <input type="file" name="response_file"
                                                       class="block w-full rounded-lg border border-dashed border-slate-300 bg-white px-2 py-2 text-xs text-slate-700 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                                <p class="text-[11px] text-slate-500 dark:text-slate-400">Puedes cargar un archivo de respuesta oficial o seleccionar un documento existente de tu oficina.</p>
                                            </div>
                                            @if(($distributionResponseDocumentOptions ?? collect())->isNotEmpty())
                                                <select name="response_document_id"
                                                        x-show="actionType === 'respond_document'"
                                                        x-cloak
                                                        class="block h-9 w-full rounded-lg border border-slate-300 bg-white px-2 text-xs text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                                    <option value="">Documento de respuesta (solo para 'Responder con documento')</option>
                                                    @foreach($distributionResponseDocumentOptions as $responseDocId => $responseDocLabel)
                                                        <option value="{{ $responseDocId }}">{{ $responseDocLabel }}</option>
                                                    @endforeach
                                                </select>
                                            @endif
                                            <button type="submit" class="inline-flex h-8 w-full items-center justify-center rounded-lg border border-sky-300/20 bg-gradient-to-r from-sky-500 to-indigo-600 px-2 text-xs font-semibold text-white">
                                                Aplicar acción
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-400">Sin acciones</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="rounded-2xl border border-white/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">Registro de Actividad</h2>
                <p class="text-sm text-slate-600 dark:text-slate-400">Trazabilidad de eventos del documento (manuales y automáticos).</p>
            </div>
        </div>

        @if(($activityLog ?? collect())->isEmpty())
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-400">
                No hay actividades registradas todavía para este documento.
            </div>
        @else
            <div class="relative pl-3">
                <div class="absolute bottom-0 left-4 top-1 w-px bg-slate-200 dark:bg-slate-700"></div>
                <div class="space-y-6">
                    @foreach($activityLog as $activity)
                        @php
                            $event = $activity->event ?: 'updated';
                            $label = match ($event) {
                                'created' => 'Documento creado',
                                'updated' => 'Documento actualizado',
                                'deleted' => 'Documento eliminado',
                                default => ucfirst((string) $event),
                            };
                            $dotColor = match ($event) {
                                'created' => 'bg-emerald-500',
                                'updated' => 'bg-sky-500',
                                'deleted' => 'bg-rose-500',
                                default => 'bg-slate-400',
                            };
                            $propertiesArray = [];

                            if ($activity->properties instanceof \Spatie\Activitylog\ActivityProperties) {
                                $propertiesArray = $activity->properties->toArray();
                            } elseif (is_array($activity->properties)) {
                                $propertiesArray = $activity->properties;
                            } elseif ($activity->properties instanceof \Illuminate\Support\Collection) {
                                $propertiesArray = $activity->properties->toArray();
                            }

                            $attributes = is_array($propertiesArray['attributes'] ?? null) ? $propertiesArray['attributes'] : [];
                            $oldValues = is_array($propertiesArray['old'] ?? null) ? $propertiesArray['old'] : [];

                            $displayDescription = match ($event) {
                                'created' => 'Se registró el documento en el sistema.',
                                'updated' => 'Se actualizaron datos del documento.',
                                'deleted' => 'El documento fue eliminado.',
                                default => ($activity->description && ! in_array(mb_strtolower((string) $activity->description), ['created', 'updated', 'deleted']) ? $activity->description : 'Evento registrado en auditoría.'),
                            };

                            $fieldLabels = [
                                'title' => 'Título',
                                'status_id' => 'Estado',
                                'category_id' => 'Categoría',
                                'assigned_to' => 'Asignado a',
                                'description' => 'Descripción',
                                'priority' => 'Prioridad',
                                'is_confidential' => 'Confidencial',
                                'is_archived' => 'Archivado',
                                'document_number' => 'Número documento',
                                'file_path' => 'Adjunto',
                            ];

                            $changeSummaries = collect();

                            if (! empty($attributes)) {
                                foreach ($attributes as $field => $newValue) {
                                    if (! array_key_exists($field, $fieldLabels)) {
                                        continue;
                                    }

                                    $oldValue = $oldValues[$field] ?? null;

                                    if ($event === 'updated' && $oldValue === $newValue) {
                                        continue;
                                    }

                                    $normalize = function ($field, $value) use ($activityUsers, $activityStatuses, $activityCategories, $priorityLabels): string {
                                        if (is_bool($value)) {
                                            return $value ? 'Sí' : 'No';
                                        }
                                        if (is_null($value) || $value === '') {
                                            return 'Vacío';
                                        }
                                        if (is_array($value)) {
                                            return json_encode($value, JSON_UNESCAPED_UNICODE);
                                        }

                                        if ($field === 'priority') {
                                            return (string) ($priorityLabels[(string) $value] ?? $value);
                                        }

                                        if ($field === 'status_id') {
                                            $key = is_numeric($value) ? (int) $value : $value;
                                            return (string) ($activityStatuses[$key] ?? $value);
                                        }

                                        if ($field === 'category_id') {
                                            $key = is_numeric($value) ? (int) $value : $value;
                                            return (string) ($activityCategories[$key] ?? $value);
                                        }

                                        if ($field === 'assigned_to') {
                                            $key = is_numeric($value) ? (int) $value : $value;
                                            return (string) ($activityUsers[$key] ?? ($value === 0 ? 'No asignado' : $value));
                                        }

                                        if (in_array($field, ['is_confidential', 'is_archived'], true)) {
                                            if ($value === 1 || $value === '1') {
                                                return 'Sí';
                                            }

                                            if ($value === 0 || $value === '0') {
                                                return 'No';
                                            }
                                        }

                                        if ($field === 'file_path') {
                                            return 'Archivo actualizado';
                                        }

                                        return (string) $value;
                                    };

                                    if ($event === 'updated' && array_key_exists($field, $oldValues)) {
                                        $changeSummaries->push([
                                            'label' => $fieldLabels[$field],
                                            'value' => $normalize($field, $oldValue).' → '.$normalize($field, $newValue),
                                        ]);
                                    } else {
                                        $changeSummaries->push([
                                            'label' => $fieldLabels[$field],
                                            'value' => $normalize($field, $newValue),
                                        ]);
                                    }
                                }
                            }
                        @endphp
                        @continue($event === 'updated' && $changeSummaries->isEmpty() && $displayDescription === 'Se actualizaron datos del documento.')
                        <div class="relative pl-10">
                            <div class="absolute left-0 top-1 flex h-8 w-8 items-center justify-center rounded-full bg-white ring-4 ring-white dark:bg-slate-900 dark:ring-slate-900">
                                <span class="h-3 w-3 rounded-full {{ $dotColor }}"></span>
                            </div>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $label }}</p>
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                        {{ $displayDescription }}
                                    </p>
                                    @if($changeSummaries->isNotEmpty())
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach($changeSummaries->take(6) as $change)
                                                <span class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                                    <span class="mr-1 font-semibold">{{ $change['label'] }}:</span>
                                                    <span>{{ Str::limit($change['value'], 60) }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="shrink-0 text-left sm:text-right">
                                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $activity->causer?->name ?? 'Sistema' }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $activity->created_at?->format('d/m/Y H:i') }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    @if($document->created_by == Auth::id())
        <section class="rounded-2xl border border-rose-200 bg-rose-50/60 p-5 shadow-sm dark:border-rose-900/40 dark:bg-rose-950/20">
            <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-rose-700 dark:text-rose-300">Zona de peligro</h2>
            <p class="mt-2 text-sm text-rose-700/90 dark:text-rose-200/90">Una vez eliminado, este documento no se puede recuperar.</p>
            <form action="{{ route('documents.destroy', $document) }}" method="POST" class="mt-4" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este documento?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex h-10 items-center justify-center rounded-xl bg-rose-600 px-4 text-sm font-semibold text-white hover:bg-rose-700">
                    Eliminar Documento
                </button>
            </form>
        </section>
    @endif
</div>
@endsection
