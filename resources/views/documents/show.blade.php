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

    <section class="grid grid-cols-1 gap-6 2xl:grid-cols-[minmax(0,1fr)_340px_360px]">
        <div class="overflow-hidden rounded-2xl border border-white/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <h2 class="text-sm font-semibold uppercase tracking-[0.14em] text-slate-600 dark:text-slate-300">Previsualización</h2>
                    @if($latestVersion)
                        <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">v{{ $latestVersion->version_number ?? $latestVersion->id }}</span>
                    @endif
                </div>
                @if($hasPreview)
                    <a href="{{ route('documents.download', $document) }}" class="text-sm font-medium text-sky-700 hover:text-sky-800 dark:text-sky-300">Abrir/Descargar</a>
                @endif
            </div>

            <div class="bg-slate-200/70 p-4 dark:bg-slate-950/60 sm:p-6">
                @if($hasPreview)
                    <div class="mx-auto flex min-h-[640px] max-w-4xl items-center justify-center rounded-lg bg-white shadow-2xl ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                        <iframe
                            src="{{ route('documents.download', $document) }}"
                            class="h-[70vh] min-h-[620px] w-full rounded-lg"
                            title="Vista previa del documento"
                        ></iframe>
                    </div>
                @else
                    <div class="mx-auto flex min-h-[500px] max-w-3xl items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 text-center dark:border-slate-700 dark:bg-slate-800/40">
                        <div class="px-6">
                            <p class="text-3xl">📄</p>
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
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-300">📄</div>
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

        <aside class="overflow-hidden rounded-2xl border border-white/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between bg-gradient-to-r from-indigo-600 to-sky-600 px-4 py-3 text-white">
                <div class="flex items-center gap-2">
                    <span>✨</span>
                    <h2 class="text-sm font-semibold uppercase tracking-[0.14em]">Insights de IA</h2>
                </div>
                @if(Auth::user()->can('create', \App\Models\DocumentAiRun::class))
                    <form method="POST" action="{{ route('documents.ai.regenerate', $document) }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-white/10 px-2 py-1 text-xs font-semibold hover:bg-white/20">Regenerar</button>
                    </form>
                @endif
            </div>

            <div class="max-h-[850px] space-y-6 overflow-y-auto p-4">
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
        </aside>
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
