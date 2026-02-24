@extends('layouts.app')

@section('title', 'Mis Documentos')

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

    $priorityBadge = function ($priorityValue) {
        return match ($priorityValue) {
            'high', 'urgent' => ['Alta', 'bg-rose-50 text-rose-700 border-rose-200'],
            'medium' => ['Media', 'bg-amber-50 text-amber-700 border-amber-200'],
            default => ['Baja', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
        };
    };

    $activeFilters = collect([
        'search' => request('search'),
        'category_id' => request('category_id'),
        'status_id' => request('status_id'),
        'priority' => request('priority'),
        'is_confidential' => request('is_confidential'),
        'date_from' => request('date_from'),
        'date_to' => request('date_to'),
    ])->filter(fn ($v) => $v !== null && $v !== '');
@endphp

<div class="space-y-6">
    <section class="rounded-2xl border border-white/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="mb-1 flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                    <span>Portal</span>
                    <span class="text-slate-300 dark:text-slate-600">/</span>
                    <span class="font-medium text-slate-700 dark:text-slate-200">Mis Documentos</span>
                </div>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white">Document Explorer</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    Administra, filtra y consulta tus documentos. Total: {{ $documents->total() }} documento(s).
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('documents.create') }}"
                   class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-sky-500 to-indigo-600 px-4 text-sm font-semibold text-white shadow-lg shadow-indigo-900/20 transition hover:from-sky-400 hover:to-indigo-500">
                    <span>Subir / Crear</span>
                </a>
                @if($documents->total() > 0)
                    <a href="{{ route('documents.export', request()->all()) }}"
                       class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                        Exportar CSV
                    </a>
                @endif
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-white/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <form method="GET" action="{{ route('documents.index') }}" class="space-y-5">
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_auto]">
                <div class="relative">
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Buscar por título, descripción o número de documento..."
                        class="block h-11 w-full rounded-xl border border-slate-300 bg-white pl-10 pr-3 text-sm text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    >
                    <svg class="pointer-events-none absolute left-3 top-3 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl border border-sky-300/20 bg-gradient-to-r from-sky-500 to-indigo-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:from-sky-400 hover:to-indigo-500">
                        Buscar
                    </button>
                    <a href="{{ route('documents.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                        Limpiar
                    </a>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Filtros rápidos</span>
                <a href="{{ route('documents.index', array_merge(request()->except('priority'), ['priority' => 'high'])) }}"
                   class="inline-flex items-center rounded-xl border px-3 py-1.5 text-xs font-medium transition {{ request('priority') === 'high' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-slate-200 bg-white text-slate-600 hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300' }}">
                    Alta prioridad
                </a>
                <a href="{{ route('documents.index', array_merge(request()->except('is_confidential'), ['is_confidential' => '1'])) }}"
                   class="inline-flex items-center rounded-xl border px-3 py-1.5 text-xs font-medium transition {{ request('is_confidential') === '1' ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-slate-200 bg-white text-slate-600 hover:border-amber-200 hover:bg-amber-50 hover:text-amber-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300' }}">
                    Confidenciales
                </a>
                <a href="{{ route('documents.index', array_merge(request()->except('date_from', 'date_to'), ['date_from' => now()->subDays(7)->format('Y-m-d'), 'date_to' => now()->format('Y-m-d')])) }}"
                   class="inline-flex items-center rounded-xl border px-3 py-1.5 text-xs font-medium transition border-slate-200 bg-white text-slate-600 hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
                    Últimos 7 días
                </a>
            </div>

            <details class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-800/40" {{ $activeFilters->isNotEmpty() ? 'open' : '' }}>
                <summary class="cursor-pointer list-none text-sm font-semibold text-slate-700 dark:text-slate-200">
                    Filtros avanzados
                </summary>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label for="category_id" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Categoría</label>
                        <select name="category_id" id="category_id" class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                            <option value="">Todas las categorías</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $translateName($category, 'Categoría') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="status_id" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Estado</label>
                        <select name="status_id" id="status_id" class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                            <option value="">Todos los estados</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status->id }}" @selected(request('status_id') == $status->id)>{{ $translateName($status, 'Estado') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="priority" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Prioridad</label>
                        <select name="priority" id="priority" class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                            <option value="">Todas</option>
                            <option value="low" @selected(request('priority') === 'low')>Baja</option>
                            <option value="medium" @selected(request('priority') === 'medium')>Media</option>
                            <option value="high" @selected(request('priority') === 'high')>Alta</option>
                            <option value="urgent" @selected(request('priority') === 'urgent')>Urgente</option>
                        </select>
                    </div>
                    <div>
                        <label for="is_confidential" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Confidencialidad</label>
                        <select name="is_confidential" id="is_confidential" class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                            <option value="">Todos</option>
                            <option value="1" @selected(request('is_confidential') === '1')>Solo confidenciales</option>
                            <option value="0" @selected(request('is_confidential') === '0')>Solo públicos</option>
                        </select>
                    </div>
                    <div>
                        <label for="date_from" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Desde</label>
                        <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                    </div>
                    <div>
                        <label for="date_to" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Hasta</label>
                        <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}" class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                    </div>
                </div>
            </details>
        </form>
    </section>

    <section class="overflow-hidden rounded-2xl border border-white/70 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @if($documents->isEmpty())
            <div class="px-6 py-14 text-center">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">📄</div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                    {{ $activeFilters->isNotEmpty() ? 'No se encontraron documentos con los filtros aplicados' : 'No tienes documentos' }}
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $activeFilters->isNotEmpty() ? 'Intenta ajustar los filtros de búsqueda.' : 'Comienza subiendo o creando tu primer documento.' }}
                </p>
                <div class="mt-5">
                    <a href="{{ $activeFilters->isNotEmpty() ? route('documents.index') : route('documents.create') }}"
                       class="inline-flex h-10 items-center justify-center rounded-xl bg-gradient-to-r from-sky-500 to-indigo-600 px-4 text-sm font-semibold text-white shadow-sm">
                        {{ $activeFilters->isNotEmpty() ? 'Ver todos los documentos' : 'Subir / Crear Documento' }}
                    </a>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-[1000px] w-full divide-y divide-slate-200 dark:divide-slate-800">
                    <thead class="bg-slate-50/70 dark:bg-slate-950/60">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Nombre</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Categoría</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Estado</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Prioridad</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Propietario</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Fecha</th>
                            <th class="px-4 py-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach($documents as $document)
                            @php
                                [$priorityLabel, $priorityClasses] = $priorityBadge(optional($document->priority)->value);
                                $statusLabel = $translateName($document->status, 'Sin estado');
                                $categoryLabel = $translateName($document->category, 'Sin categoría');
                                $statusDotClass = str_contains(mb_strtolower($statusLabel), 'aprob') ? 'bg-emerald-500' : (str_contains(mb_strtolower($statusLabel), 'proceso') ? 'bg-amber-500' : 'bg-slate-400');
                            @endphp
                            <tr class="group hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                <td class="px-6 py-4">
                                    <a href="{{ route('documents.show', $document) }}" class="flex items-center gap-4">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-900/30 dark:text-sky-300">
                                            <span class="text-lg">📄</span>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="truncate text-sm font-semibold text-slate-900 transition group-hover:text-sky-700 dark:text-white dark:group-hover:text-sky-300">
                                                {{ $document->title }}
                                            </div>
                                            <div class="mt-0.5 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                                <span>{{ $document->document_number ?: 'Sin número' }}</span>
                                                @if($document->is_confidential)
                                                    <span class="inline-flex items-center rounded-md border border-rose-200 bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700">Confidencial</span>
                                                @endif
                                            </div>
                                        </div>
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                        {{ $categoryLabel }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                        <span class="h-2 w-2 rounded-full {{ $statusDotClass }}"></span>
                                        <span>{{ $statusLabel }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-md border px-2 py-1 text-xs font-semibold {{ $priorityClasses }}">
                                        {{ $priorityLabel }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300">
                                    {{ $document->creator?->name ?? 'Sin propietario' }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-slate-500 dark:text-slate-400">
                                    {{ $document->created_at?->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="flex justify-end gap-2 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition">
                                        <a href="{{ route('documents.show', $document) }}" class="rounded-lg px-2 py-1 text-xs font-medium text-sky-700 hover:bg-sky-50 dark:text-sky-300 dark:hover:bg-slate-800">Ver</a>
                                        @if($document->created_by == Auth::id() || $document->assigned_to == Auth::id())
                                            <a href="{{ route('documents.edit', $document) }}" class="rounded-lg px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-50 dark:text-indigo-300 dark:hover:bg-slate-800">Editar</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                {{ $documents->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
