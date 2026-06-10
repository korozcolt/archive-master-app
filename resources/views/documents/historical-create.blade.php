@extends('layouts.app')

@section('title', 'Carga Histórica')

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

    $locationOptions = $physicalLocations
        ->map(function ($location) {
            $segments = collect(explode(' / ', $location->full_path))
                ->filter()
                ->values()
                ->all();

            return [
                'id' => (int) $location->id,
                'code' => (string) ($location->code ?? ''),
                'path' => (string) $location->full_path,
                'segments' => $segments,
                'label' => (string) ($location->code ? "{$location->code} - {$location->full_path}" : $location->full_path),
            ];
        })
        ->values();

    $historicalBenefits = [
        'Sin radicación ni recibido',
        'Custodia directa en archivo central',
        'Consulta transversal según nivel de acceso',
    ];

    $requiredFields = [
        'Documentos digitalizados',
        'Categoría temática',
        'Dependencia productora original',
        'Ubicación física',
        'Nivel de acceso',
    ];
@endphp

<div
    class="space-y-6"
    x-data="{
        dragging: false,
        selectedFiles: [],
        selectedLocationId: @js(old('physical_location_id')),
        selectedLocation: null,
        locationSearch: '',
        locationOptions: @js($locationOptions),
        init() {
            this.syncSelectedLocation();
        },
        handleFiles(event) {
            const files = Array.from(event.target.files || []);
            this.selectedFiles = files.map((file) => ({
                name: file.name,
                size: this.formatBytes(file.size),
                extension: this.extensionFrom(file.name),
            }));
        },
        clearFiles() {
            this.selectedFiles = [];

            if (this.$refs.filesInput) {
                this.$refs.filesInput.value = '';
            }
        },
        extensionFrom(name) {
            const parts = String(name || '').split('.');

            return parts.length > 1 ? parts.pop().toUpperCase() : 'DOC';
        },
        formatBytes(bytes) {
            if (!bytes) {
                return '0 KB';
            }

            const units = ['B', 'KB', 'MB', 'GB'];
            let size = bytes;
            let unitIndex = 0;

            while (size >= 1024 && unitIndex < units.length - 1) {
                size /= 1024;
                unitIndex++;
            }

            return `${size.toFixed(size >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
        },
        filteredLocations() {
            const search = this.locationSearch.trim().toLowerCase();

            if (!search) {
                return this.locationOptions.slice(0, 8);
            }

            return this.locationOptions
                .filter((location) => {
                    const haystack = [
                        location.code,
                        location.path,
                        ...(location.segments || []),
                    ].join(' ').toLowerCase();

                    return haystack.includes(search);
                })
                .slice(0, 8);
        },
        selectLocation(id) {
            this.selectedLocationId = String(id);
            this.locationSearch = '';
            this.syncSelectedLocation();
        },
        clearSelectedLocation() {
            this.selectedLocationId = '';
            this.selectedLocation = null;
            this.locationSearch = '';
        },
        syncSelectedLocation() {
            const selectedId = Number(this.selectedLocationId);
            this.selectedLocation = this.locationOptions.find((location) => Number(location.id) === selectedId) || null;
        },
    }"
>
    <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-sm">
        <div class="grid gap-0 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="p-6 sm:p-7">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-200">
                        Archivo Central
                    </span>
                    <span class="inline-flex items-center rounded-full border border-sky-500/20 bg-sky-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-200">
                        Flujo exclusivo de histórico
                    </span>
                </div>

                <h1 class="mt-4 text-3xl font-semibold tracking-tight text-white">Carga histórica</h1>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">
                    Incorpora documentos ya digitalizados al archivo central sin pasar por recepción, radicación ni distribución inicial. Cada archivo crea un documento histórico independiente y queda listo para consulta interna según su nivel de acceso.
                </p>

                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                    @foreach($historicalBenefits as $benefit)
                        <div class="rounded-2xl border border-slate-800 bg-slate-800/70 px-4 py-3 text-sm font-medium text-slate-200">
                            {{ $benefit }}
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="border-t border-slate-800 bg-slate-950/70 p-6 lg:border-t-0 lg:border-l">
                <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-400">Tratamiento automático</h2>
                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="text-slate-400">Custodio</dt>
                        <dd class="mt-1 font-semibold text-white">
                            {{ $translateName($centralArchiveDepartment, 'Archivo Central') }}
                            <span class="text-slate-400">({{ $centralArchiveDepartment->code }})</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Estado inicial</dt>
                        <dd class="mt-1 font-semibold text-white">{{ $defaultStatus?->name ?? 'Archivado' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-400">Resultado</dt>
                        <dd class="mt-1 text-slate-300">Queda en archivo central con trazabilidad de procedencia y ubicación física obligatoria.</dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>

    <form action="{{ route('documents.historical.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="space-y-6">
                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-sm">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-white">1. Archivos a incorporar</h2>
                            <p class="mt-1 text-sm text-slate-400">
                                Carga uno o varios documentos digitalizados. El título de cada registro se tomará del nombre del archivo.
                            </p>
                        </div>
                        <span class="inline-flex items-center rounded-full border border-slate-700 bg-slate-800 px-3 py-1 text-xs font-semibold text-slate-300">
                            PDF, Office e imagen
                        </span>
                    </div>

                    <div class="mt-5">
                        <label
                            for="files"
                            class="group block cursor-pointer rounded-2xl border-2 border-dashed border-slate-700 bg-slate-950/50 p-6 transition hover:border-amber-400 hover:bg-amber-500/5"
                            :class="dragging ? 'border-amber-500 bg-amber-500/10' : ''"
                            @dragover.prevent="dragging = true"
                            @dragleave.prevent="dragging = false"
                            @drop.prevent="dragging = false; $refs.filesInput.files = $event.dataTransfer.files; handleFiles({ target: $refs.filesInput });"
                        >
                            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                <div class="flex items-start gap-4">
                                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-amber-500/15 text-amber-200">
                                        @svg('heroicon-o-archive-box-arrow-down', 'h-7 w-7')
                                    </div>
                                    <div>
                                        <p class="text-base font-semibold text-white">Documentos digitalizados <span class="text-rose-400">*</span></p>
                                        <p class="mt-1 text-sm text-slate-400">
                                            Arrastra archivos aquí o selecciona un lote desde tu equipo.
                                        </p>
                                        <p class="mt-2 text-xs uppercase tracking-[0.14em] text-slate-500">
                                            Cada archivo crea un documento histórico independiente
                                        </p>
                                    </div>
                                </div>

                                <div class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-700 bg-slate-800 px-4 text-sm font-semibold text-slate-100 shadow-sm transition group-hover:border-amber-400/40 group-hover:text-amber-200">
                                    Seleccionar archivos
                                </div>
                            </div>
                        </label>

                        <input
                            x-ref="filesInput"
                            type="file"
                            name="files[]"
                            id="files"
                            multiple
                            required
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                            class="sr-only"
                            @change="handleFiles($event)"
                        >

                        @error('files')
                            <p class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                        @error('files.*')
                            <p class="mt-2 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror

                        <div x-show="selectedFiles.length > 0" x-cloak class="mt-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold text-slate-200">
                                    Lote seleccionado <span class="text-slate-400" x-text="`(${selectedFiles.length} archivo${selectedFiles.length === 1 ? '' : 's'})`"></span>
                                </p>
                                <button type="button" @click="clearFiles()" class="text-sm font-medium text-slate-400 transition hover:text-rose-300">
                                    Limpiar
                                </button>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <template x-for="file in selectedFiles" :key="file.name + file.size">
                                    <div class="rounded-2xl border border-slate-800 bg-slate-950/40 px-4 py-3">
                                        <div class="flex items-start gap-3">
                                            <div class="inline-flex min-w-[52px] items-center justify-center rounded-xl bg-slate-900 px-2 py-1 text-xs font-semibold tracking-[0.14em] text-white dark:bg-slate-700" x-text="file.extension"></div>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium text-white" x-text="file.name"></p>
                                                <p class="mt-1 text-xs text-slate-400" x-text="file.size"></p>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-white">2. Descripción archivística</h2>
                    <p class="mt-1 text-sm text-slate-400">
                        Describe el expediente para que cualquier oficina autorizada pueda encontrarlo por procedencia, asunto o clasificación.
                    </p>

                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="category_id" class="mb-1.5 block text-sm font-medium text-slate-200">Categoría temática <span class="text-rose-400">*</span></label>
                            <select name="category_id" id="category_id" required class="block h-11 w-full rounded-xl border border-slate-700 bg-slate-800 px-3 text-sm text-white shadow-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20">
                                <option value="">Seleccionar categoría</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $translateName($category, 'Categoría') }}</option>
                                @endforeach
                            </select>
                            @error('category_id')
                                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="original_department_id" class="mb-1.5 block text-sm font-medium text-slate-200">Dependencia productora original <span class="text-rose-400">*</span></label>
                            <select name="original_department_id" id="original_department_id" required class="block h-11 w-full rounded-xl border border-slate-700 bg-slate-800 px-3 text-sm text-white shadow-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20">
                                <option value="">Seleccionar dependencia</option>
                                @foreach($producerDepartments as $department)
                                    <option value="{{ $department->id }}" @selected((string) old('original_department_id') === (string) $department->id)>{{ $translateName($department, 'Dependencia') }}</option>
                                @endforeach
                            </select>
                            @error('original_department_id')
                                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="description" class="mb-1.5 block text-sm font-medium text-slate-200">Descripción general</label>
                            <textarea name="description" id="description" rows="4" class="block w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-3 text-sm text-white shadow-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20">{{ old('description') }}</textarea>
                            <p class="mt-1.5 text-xs text-slate-400">Úsala para resumir asunto, contenido o contexto del lote digitalizado.</p>
                            @error('description')
                                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-white">3. Contexto y referencia</h2>
                    <p class="mt-1 text-sm text-slate-400">
                        Estos datos fortalecen la búsqueda transversal y la trazabilidad frente al inventario físico.
                    </p>

                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="date_start" class="mb-1.5 block text-sm font-medium text-slate-200">Fecha inicial</label>
                            <input type="date" name="date_start" id="date_start" value="{{ old('date_start') }}" class="block h-11 w-full rounded-xl border border-slate-700 bg-slate-800 px-3 text-sm text-white shadow-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20">
                        </div>
                        <div>
                            <label for="date_end" class="mb-1.5 block text-sm font-medium text-slate-200">Fecha final</label>
                            <input type="date" name="date_end" id="date_end" value="{{ old('date_end') }}" class="block h-11 w-full rounded-xl border border-slate-700 bg-slate-800 px-3 text-sm text-white shadow-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20">
                        </div>
                        <div>
                            <label for="box" class="mb-1.5 block text-sm font-medium text-slate-200">Caja</label>
                            <input type="text" name="box" id="box" value="{{ old('box') }}" class="block h-11 w-full rounded-xl border border-slate-700 bg-slate-800 px-3 text-sm text-white shadow-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20">
                        </div>
                        <div>
                            <label for="folder" class="mb-1.5 block text-sm font-medium text-slate-200">Carpeta</label>
                            <input type="text" name="folder" id="folder" value="{{ old('folder') }}" class="block h-11 w-full rounded-xl border border-slate-700 bg-slate-800 px-3 text-sm text-white shadow-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20">
                        </div>
                        <div>
                            <label for="volume" class="mb-1.5 block text-sm font-medium text-slate-200">Tomo / volumen</label>
                            <input type="text" name="volume" id="volume" value="{{ old('volume') }}" class="block h-11 w-full rounded-xl border border-slate-700 bg-slate-800 px-3 text-sm text-white shadow-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20">
                        </div>
                        <div>
                            <label for="reference_code" class="mb-1.5 block text-sm font-medium text-slate-200">Código de referencia</label>
                            <input type="text" name="reference_code" id="reference_code" value="{{ old('reference_code') }}" class="block h-11 w-full rounded-xl border border-slate-700 bg-slate-800 px-3 text-sm text-white shadow-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20">
                        </div>
                        <div class="md:col-span-2">
                            <label for="keywords" class="mb-1.5 block text-sm font-medium text-slate-200">Palabras clave</label>
                            <textarea name="keywords" id="keywords" rows="3" class="block w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-3 text-sm text-white shadow-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20">{{ old('keywords') }}</textarea>
                            <p class="mt-1.5 text-xs text-slate-400">Separa temas o nombres con coma para mejorar la consulta posterior.</p>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="space-y-6 xl:sticky xl:top-24 xl:self-start">
                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-white">4. Custodia y acceso</h2>
                    <p class="mt-1 text-sm text-slate-400">
                        Define dónde queda el físico y quién podrá consultar el documento digital dentro de la empresa.
                    </p>

                    <div class="mt-5 space-y-5">
                        <div>
                            <label for="location_search" class="mb-1.5 block text-sm font-medium text-slate-200">Buscar ubicación física <span class="text-rose-400">*</span></label>
                            <input
                                id="location_search"
                                type="text"
                                x-model="locationSearch"
                                placeholder="Ej. Estante 14, ENT-03, Sótano"
                                class="block h-11 w-full rounded-xl border border-slate-700 bg-slate-800 px-3 text-sm text-white shadow-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20"
                            >
                            <p class="mt-1.5 text-xs text-slate-400">
                                Busca y elige una ubicación. Si te equivocas, puedes cambiarla o limpiarla antes de guardar.
                            </p>

                            <select name="physical_location_id" x-model="selectedLocationId" @change="syncSelectedLocation()" class="sr-only">
                                <option value="">Seleccionar ubicación</option>
                                @foreach($physicalLocations as $location)
                                    <option value="{{ $location->id }}">{{ $location->code ? "{$location->code} - {$location->full_path}" : $location->full_path }}</option>
                                @endforeach
                            </select>

                            <div class="mt-3 rounded-2xl border border-slate-800 bg-slate-950/40 p-2">
                                <div class="max-h-64 space-y-2 overflow-y-auto">
                                    <template x-for="location in filteredLocations()" :key="location.id">
                                        <button
                                            type="button"
                                            @click="selectLocation(location.id)"
                                            class="w-full rounded-xl border px-3 py-3 text-left transition"
                                            :class="String(selectedLocationId) === String(location.id)
                                                ? 'border-amber-400 bg-amber-50 text-amber-900 dark:border-amber-400/40 dark:bg-amber-500/10 dark:text-amber-100'
                                                        : 'border-slate-800 bg-slate-900 text-slate-200 hover:border-slate-700 hover:bg-slate-800'"
                                        >
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="text-sm font-semibold" x-text="location.code || 'Sin código'"></p>
                                                    <p class="mt-1 text-xs leading-5 text-slate-400" x-text="location.path"></p>
                                                </div>
                                                <span
                                                    class="inline-flex rounded-full px-2 py-1 text-[11px] font-semibold uppercase tracking-[0.14em]"
                                                    :class="String(selectedLocationId) === String(location.id)
                                                        ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200'
                                                        : 'bg-slate-800 text-slate-300'"
                                                    x-text="String(selectedLocationId) === String(location.id) ? 'Seleccionada' : 'Elegir'"
                                                >
                                                </span>
                                            </div>
                                        </button>
                                    </template>

                                    <div x-show="filteredLocations().length === 0" x-cloak class="rounded-xl border border-dashed border-slate-700 px-4 py-5 text-sm text-slate-400">
                                        No hay coincidencias con esa búsqueda.
                                    </div>
                                </div>
                            </div>

                            @error('physical_location_id')
                                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="rounded-2xl border border-slate-800 bg-slate-950/40 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-white">Ubicación seleccionada</p>
                                    <p class="mt-1 text-xs text-slate-400">Se guardará como ubicación inicial del físico en archivo central.</p>
                                </div>
                                <div
                                    class="inline-flex min-h-9 max-w-full items-center self-start rounded-xl bg-slate-800 px-3 py-2 text-xs font-semibold tracking-[0.14em] text-white sm:self-auto"
                                    x-show="selectedLocation"
                                    x-cloak
                                    x-text="selectedLocation?.code"
                                ></div>
                            </div>

                            <div x-show="selectedLocation" x-cloak class="mt-4 space-y-3">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        @click="locationSearch = ''; $nextTick(() => document.getElementById('location_search')?.focus())"
                                        class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-700 bg-slate-800 px-3 text-xs font-semibold text-slate-200 transition hover:bg-slate-700"
                                    >
                                        Cambiar selección
                                    </button>
                                    <button
                                        type="button"
                                        @click="clearSelectedLocation(); $nextTick(() => document.getElementById('location_search')?.focus())"
                                        class="inline-flex h-9 items-center justify-center rounded-xl border border-rose-500/20 bg-rose-500/10 px-3 text-xs font-semibold text-rose-200 transition hover:bg-rose-500/15"
                                    >
                                        Limpiar
                                    </button>
                                </div>
                                <div class="rounded-xl border border-slate-800 bg-slate-900 px-4 py-3 text-sm text-slate-200" x-text="selectedLocation?.path"></div>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="segment in (selectedLocation?.segments || [])" :key="segment">
                                        <span class="inline-flex items-center rounded-full border border-slate-700 bg-slate-800 px-3 py-1 text-xs font-medium text-slate-300" x-text="segment"></span>
                                    </template>
                                </div>
                            </div>

                            <div x-show="!selectedLocation" class="mt-4 rounded-xl border border-dashed border-slate-700 px-4 py-4 text-sm text-slate-400">
                                Busca por código, estante, entrepaño o nivel y elige una ubicación para continuar.
                            </div>
                        </div>

                        <div>
                            <label for="access_level" class="mb-1.5 block text-sm font-medium text-slate-200">Nivel de acceso <span class="text-rose-400">*</span></label>
                            <select name="access_level" id="access_level" required class="block h-11 w-full rounded-xl border border-slate-700 bg-slate-800 px-3 text-sm text-white shadow-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20">
                                @foreach(\App\Enums\DocumentAccessLevel::cases() as $accessLevel)
                                    <option value="{{ $accessLevel->value }}" @selected(old('access_level', \App\Enums\DocumentAccessLevel::Interno->value) === $accessLevel->value)>{{ $accessLevel->getLabel() }}</option>
                                @endforeach
                            </select>
                            @error('access_level')
                                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-amber-500/20 bg-amber-500/10 p-6 shadow-sm">
                    <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-amber-200">Resumen de incorporación</h2>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                        <div class="rounded-2xl border border-amber-500/20 bg-slate-900/60 px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.14em] text-amber-200/80">Campos críticos</p>
                            <p class="mt-2 text-sm font-medium text-slate-100">{{ count($requiredFields) }} datos obligatorios</p>
                        </div>
                        <div class="rounded-2xl border border-amber-500/20 bg-slate-900/60 px-4 py-3">
                            <p class="text-xs uppercase tracking-[0.14em] text-amber-200/80">Ubicaciones disponibles</p>
                            <p class="mt-2 text-sm font-medium text-slate-100">{{ $physicalLocations->count() }} espacios activos</p>
                        </div>
                    </div>

                    <ul class="mt-4 space-y-2 text-sm text-amber-100">
                        @foreach($requiredFields as $field)
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5 text-amber-600 dark:text-amber-300">•</span>
                                <span>{{ $field }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="flex flex-col gap-3">
                    <button type="submit" class="inline-flex h-12 items-center justify-center rounded-xl bg-gradient-to-r from-amber-500 to-orange-600 px-5 text-sm font-semibold text-white shadow-lg shadow-orange-900/20 transition hover:from-amber-400 hover:to-orange-500">
                        Incorporar al archivo central
                    </button>
                    <a href="{{ route('documents.index') }}" class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-700 bg-slate-800 px-4 text-sm font-semibold text-slate-200 shadow-sm transition hover:bg-slate-700">
                        Volver al listado
                    </a>
                </div>
            </aside>
        </section>
    </form>
</div>
@endsection
