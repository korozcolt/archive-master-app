@extends('layouts.app')

@section('title', 'Subir Nuevos Documentos')

@section('content')
@php
    $translateName = function ($model, string $fallback = 'Sin nombre') {
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

    $steps = [
        ['key' => 1, 'title' => 'Selección de archivos', 'subtitle' => 'Subir o arrastrar y soltar'],
        ['key' => 2, 'title' => 'Metadatos', 'subtitle' => 'Agregar detalles y etiquetas'],
        ['key' => 3, 'title' => 'Configuración', 'subtitle' => 'Acceso y recibido'],
        ['key' => 4, 'title' => 'Revisión', 'subtitle' => 'Confirmar carga'],
    ];
    $categoryOptions = $categories->map(fn ($category) => [
        'id' => (int) $category->id,
        'label' => $translateName($category, 'Categoría'),
    ])->values();
    $statusOptions = $statuses->map(fn ($status) => [
        'id' => (int) $status->id,
        'label' => $translateName($status, 'Estado'),
    ])->values();
    $iconCatalogExtensions = [
        'pdf',
        'doc', 'docx', 'odt', 'rtf', 'pages',
        'xls', 'xlsx', 'csv', 'ods', 'numbers',
        'ppt', 'pptx', 'odp', 'key',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tif', 'tiff', 'heic', 'heif', 'avif',
        'zip', 'rar', '7z', 'tar', 'gz', 'tgz', 'bz2', 'xz',
        'txt', 'md', 'log', 'ini', 'env', 'conf',
        'json', 'xml', 'yml', 'yaml', 'sql', 'js', 'ts', 'jsx', 'tsx', 'php', 'py', 'java', 'go', 'rs', 'css', 'scss', 'html',
        'mp3', 'wav', 'ogg', 'm4a', 'flac',
        'mp4', 'mov', 'avi', 'mkv', 'webm',
        'dwg', 'dxf', 'step',
        'ai', 'psd', 'fig',
        'unknown',
    ];
    $extensionUiMap = collect($iconCatalogExtensions)
        ->mapWithKeys(function (string $extension) {
            $meta = \App\Support\FileExtensionIcon::meta($extension === 'unknown' ? '' : $extension);

            return [
                $extension => [
                    'svg' => svg($meta['icon'], 'h-5 w-5')->toHtml(),
                    'bgClass' => trim($meta['bg'].' '.$meta['fg']),
                    'badgeClass' => $meta['badge'],
                    'label' => $meta['label'],
                ],
            ];
        })
        ->all();
@endphp

<form
    action="{{ route('documents.store') }}"
    method="POST"
    enctype="multipart/form-data"
    class="space-y-6"
    x-data="uploadDocumentWizard()"
    @submit.prevent="submitForm($event)"
>
    @csrf
    <input type="hidden" name="draft_id" x-model="draftId">

    <div class="rounded-2xl border border-white/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-600 dark:text-sky-400">Documentos</p>
                <h1 class="mt-1 text-3xl font-black tracking-tight text-slate-900 dark:text-white">Subir Nuevos Documentos</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Modelo de carga guiada para uno o varios documentos, con edición rápida y revisión antes de crear.
                </p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('documents.index') }}"
                   class="inline-flex h-10 items-center justify-center rounded-lg px-4 text-sm font-medium text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                    Cancelar
                </a>
                <button type="button"
                        @click="saveDraft()"
                        :disabled="isSavingDraft || isUploading"
                        class="inline-flex h-10 items-center justify-center rounded-lg px-4 text-sm font-medium text-white transition"
                        :class="(isSavingDraft || isUploading) ? 'cursor-not-allowed bg-slate-400 dark:bg-slate-700' : 'bg-slate-700 hover:bg-slate-800 dark:bg-slate-700 dark:hover:bg-slate-600'">
                    <span x-show="!isSavingDraft">Guardar borrador</span>
                    <span x-show="isSavingDraft">Guardando...</span>
                </button>
            </div>
        </div>
        <div x-show="draftMessage" x-cloak class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-300" x-text="draftMessage"></div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <aside class="lg:col-span-3 lg:border-r lg:border-slate-200 lg:pr-6 lg:dark:border-slate-800">
            <div class="sticky top-24">
                <div class="flex gap-2 overflow-x-auto pb-2 lg:flex-col lg:overflow-visible">
                    @foreach($steps as $index => $step)
                        <button type="button"
                                @click="goToStep({{ $step['key'] }})"
                                class="min-w-[210px] rounded-lg p-3 text-left transition"
                                :class="currentStep === {{ $step['key'] }}
                                    ? 'bg-white shadow-sm ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700'
                                    : 'opacity-70 hover:bg-slate-50 dark:hover:bg-slate-800/50'">
                            <div class="flex items-center gap-4">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold"
                                     :class="currentStep === {{ $step['key'] }}
                                        ? 'bg-sky-600 text-white'
                                        : (currentStep > {{ $step['key'] }} ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'border-2 border-slate-300 text-slate-500 dark:border-slate-600 dark:text-slate-400')">
                                    <span x-show="currentStep <= {{ $step['key'] }}">{{ $step['key'] }}</span>
                                    <span x-show="currentStep > {{ $step['key'] }}">✓</span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $step['title'] }}</span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $step['subtitle'] }}</span>
                                </div>
                            </div>
                        </button>

                        @if(! $loop->last)
                            <div class="mx-4 hidden h-5 w-px bg-slate-200 lg:ml-4 lg:block dark:bg-slate-700"></div>
                        @endif
                    @endforeach
                </div>
            </div>
        </aside>

        <section class="lg:col-span-9 space-y-6">
            <div x-show="currentStep === 1" x-cloak x-ref="step1" class="space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div
                        class="group relative overflow-hidden rounded-xl border-2 border-dashed border-sky-300/40 bg-slate-50/70 p-8 text-center transition hover:border-sky-500 dark:border-sky-700/40 dark:bg-slate-950/30 md:p-10"
                        :class="{ 'ring-2 ring-sky-300/40 border-sky-500': isDragging }"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleDrop($event)"
                    >
                        <div class="absolute inset-0 bg-gradient-to-tr from-sky-500/5 to-transparent opacity-0 transition group-hover:opacity-100"></div>

                        <div class="relative mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-sky-500/10 text-sky-600 transition group-hover:scale-105 dark:text-sky-300">
                            <span class="text-3xl">☁️</span>
                        </div>
                        <h2 class="relative text-xl font-bold text-slate-900 dark:text-white">Arrastra y suelta archivos aquí</h2>
                        <p class="relative mt-2 text-sm text-slate-500 dark:text-slate-400">
                            Soporta PDF, DOCX, XLSX y PNG. El límite real depende de la configuración del servidor.
                        </p>

                        <div class="relative my-5 flex items-center justify-center gap-4">
                            <div class="h-px w-16 bg-slate-200 dark:bg-slate-700"></div>
                            <span class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">O</span>
                            <div class="h-px w-16 bg-slate-200 dark:bg-slate-700"></div>
                        </div>

                        <div class="relative flex flex-wrap justify-center gap-3">
                            <button type="button"
                                    @click="$refs.bulkFiles.click()"
                                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-900 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:hover:bg-slate-700">
                                <span>📂</span>
                                Seleccionar archivos
                            </button>
                            <button type="button"
                                    @click="$refs.singleFile.click()"
                                    class="inline-flex items-center gap-2 rounded-lg border border-sky-300/20 bg-gradient-to-r from-sky-500 to-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:from-sky-400 hover:to-indigo-500">
                                <span>📄</span>
                                Archivo único
                            </button>
                        </div>

                        <input
                            type="file"
                            x-ref="bulkFiles"
                            name="files[]"
                            multiple
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                            class="sr-only"
                            @change="handleBulkSelection($event)"
                        >
                        <input
                            type="file"
                            x-ref="singleFile"
                            name="file"
                            id="file"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                            class="sr-only"
                            @change="handleSingleSelection($event)"
                        >
                    </div>

                    @error('file')
                        <p class="mt-3 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                    @error('files')
                        <p class="mt-3 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                    @error('files.*')
                        <p class="mt-3 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                    @enderror
                </div>

                <div class="space-y-4" x-show="rows.length > 0" x-cloak>
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Archivos cargados</h3>
                        <button type="button" @click="clearRows()" class="text-sm font-medium text-sky-700 hover:underline dark:text-sky-300">Limpiar todo</button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(row, index) in rows" :key="row.uid">
                            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:border-sky-300 dark:border-slate-800 dark:bg-slate-900">
                                <div class="flex items-start gap-4">
                                    <div class="relative mt-1 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset ring-white/10"
                                         :class="fileIconMeta(row.extension).bgClass">
                                        <span x-html="fileIconSvg(row.extension, 'h-5 w-5')"></span>
                                        <span class="absolute -bottom-1.5 -right-1.5 inline-flex min-w-[28px] items-center justify-center rounded-md border px-1 py-0.5 text-[10px] font-bold leading-none shadow-sm"
                                              :class="fileIconMeta(row.extension).badgeClass"
                                              x-text="fileTypeLabel(row.extension)"></span>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white" x-text="row.fileName"></p>
                                            <template x-if="row.uploadStatus === 'uploading'">
                                                <span class="inline-flex items-center gap-2 text-xs font-medium text-sky-600 dark:text-sky-300">
                                                    <span class="inline-flex h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent"></span>
                                                    <span x-text="row.progress > 0 ? `Subiendo ${row.progress}%` : 'Subiendo...'"></span>
                                                </span>
                                            </template>
                                            <template x-if="row.uploadStatus === 'ready'">
                                                <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                                    <span>●</span> Listo
                                                </span>
                                            </template>
                                            <template x-if="row.uploadStatus === 'error'">
                                                <span class="inline-flex items-center gap-1 text-xs font-medium text-rose-600 dark:text-rose-300">
                                                    <span>●</span> Error
                                                </span>
                                            </template>
                                        </div>

                                        <div class="mb-3 flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                            <span x-text="row.sizeLabel"></span>
                                            <span class="h-1 w-1 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                                            <span x-text="row.uploadStatus === 'uploading' ? 'Cargando archivo al servidor...' : (row.uploadStatus === 'error' ? 'Falló la carga' : 'Agregado hace un momento')"></span>
                                        </div>

                                        <div x-show="row.uploadStatus === 'uploading' || row.uploadStatus === 'error'" x-cloak class="mb-3">
                                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                                                <div class="h-full rounded-full transition-all duration-200"
                                                     :class="row.uploadStatus === 'error' ? 'bg-rose-500' : 'bg-gradient-to-r from-sky-500 to-indigo-600'"
                                                     :style="`width: ${row.uploadStatus === 'error' ? 100 : Math.max(row.progress || 5, 5)}%`"></div>
                                            </div>
                                            <p x-show="row.uploadStatus === 'error' && row.uploadError" x-cloak class="mt-1 text-xs text-rose-600 dark:text-rose-300" x-text="row.uploadError"></p>
                                        </div>

                                        <div class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_120px]">
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Título</label>
                                                <input type="text"
                                                       :data-item-id="row.id || ''"
                                                       :name="`bulk_items[${index}][title]`"
                                                       x-model="row.title"
                                                       required
                                                       :disabled="row.uploadStatus === 'uploading'"
                                                       class="block h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                                <input type="hidden" :name="`bulk_items[${index}][id]`" :value="row.id || ''">
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Tipo</label>
                                                <div class="flex h-10 items-center gap-2 rounded-lg border px-3 text-sm font-medium"
                                                     :class="fileIconMeta(row.extension).badgeClass"
                                                >
                                                    <span class="inline-flex items-center" x-html="fileIconSvg(row.extension, 'h-4 w-4')"></span>
                                                    <span x-text="fileTypeLabel(row.extension)"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="button" @click="removeRow(index)" :disabled="row.uploadStatus === 'uploading'" class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-rose-950/20 dark:hover:text-rose-300">
                                        ✕
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div x-show="currentStep === 2" x-cloak x-ref="step2" class="space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="mb-5">
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">Metadatos</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            <span x-show="rows.length === 0" x-cloak>Configura el título del documento y sus metadatos.</span>
                            <span x-show="rows.length > 0" x-cloak>Configura metadatos por defecto del lote y ajusta por archivo cuando sea necesario.</span>
                        </p>
                    </div>

                    <div class="space-y-5">
                        <div x-show="rows.length === 0" x-cloak>
                            <label for="title" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">
                                Título <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                name="title"
                                id="title"
                                value="{{ old('title') }}"
                                :required="rows.length === 0"
                                :disabled="rows.length > 0"
                                class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                placeholder="Ej. Contrato de mantenimiento sede norte"
                            >
                            <p x-show="rows.length > 0" x-cloak class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                                En carga múltiple, el título se define por archivo en Selección de archivos.
                            </p>
                            @error('title')
                                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-show="rows.length > 0" x-cloak class="rounded-xl border border-sky-200 bg-sky-50/70 p-4 dark:border-sky-900/40 dark:bg-sky-950/20">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5 flex h-6 w-6 items-center justify-center rounded-full bg-sky-500/10 text-sky-700 dark:text-sky-300">ℹ️</div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Títulos por archivo ya definidos</p>
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                        En carga múltiple, cada documento usa el título configurado en <span class="font-medium">Selección de archivos</span> (edición rápida del lote).
                                    </p>
                                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                        Este paso define valores por defecto para el lote y permite sobrescribir categoría/estado por documento.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label for="description" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Descripción</label>
                            <textarea name="description" id="description" rows="4"
                                      class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                      placeholder="Contexto, contenido o notas relevantes del documento...">{{ old('description') }}</textarea>
                            @error('description')
                                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label for="category_id" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Categoría <span class="text-rose-500">*</span></label>
                                <select name="category_id" id="category_id" required
                                        class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                    <option value="">Seleccionar categoría</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $translateName($category, 'Categoría') }}</option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="status_id" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Estado <span class="text-rose-500">*</span></label>
                                <select name="status_id" id="status_id" required
                                        class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                    <option value="">Seleccionar estado</option>
                                    @foreach($statuses as $status)
                                        <option value="{{ $status->id }}" @selected(old('status_id') == $status->id)>{{ $translateName($status, 'Estado') }}</option>
                                    @endforeach
                                </select>
                                @error('status_id')
                                    <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div x-show="rows.length > 0" x-cloak class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-800/40">
                            <div class="mb-4 flex flex-col gap-1">
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Ajustes por archivo (opcional)</h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    Si un documento requiere categoría o estado diferente al valor por defecto del lote, ajústalo aquí.
                                </p>
                            </div>

                            <div class="space-y-3">
                                <template x-for="(row, index) in rows" :key="`${row.uid}-metadata`">
                                    <div class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                                        <div class="mb-3 flex items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-slate-900 dark:text-white" x-text="row.title || row.fileName"></p>
                                                <p class="truncate text-xs text-slate-500 dark:text-slate-400" x-text="row.fileName"></p>
                                            </div>
                                            <button type="button"
                                                    @click="clearRowMetadata(index)"
                                                    class="inline-flex h-8 items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                                Usar default
                                            </button>
                                        </div>

                                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Categoría (opcional)</label>
                                                <select :name="`bulk_items[${index}][category_id]`"
                                                        x-model="row.category_id"
                                                        class="block h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                                    <option value="">Usar categoría del lote</option>
                                                    @foreach($categoryOptions as $option)
                                                        <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Estado (opcional)</label>
                                                <select :name="`bulk_items[${index}][status_id]`"
                                                        x-model="row.status_id"
                                                        class="block h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                                    <option value="">Usar estado del lote</option>
                                                    @foreach($statusOptions as $option)
                                                        <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div x-show="currentStep === 3" x-cloak x-ref="step3" class="space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="mb-5">
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">Configuración</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Acceso, prioridad y parámetros operativos para los archivos cargados.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-5">
                        <div>
                            <label for="priority" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Prioridad <span class="text-rose-500">*</span></label>
                            <select name="priority" id="priority" required
                                    class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                <option value="low" @selected(old('priority') == 'low')>Baja</option>
                                <option value="medium" @selected(old('priority', 'medium') == 'medium')>Media</option>
                                <option value="high" @selected(old('priority') == 'high')>Alta</option>
                            </select>
                            @error('priority')
                                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/40">
                            <input type="checkbox"
                                   name="is_confidential"
                                   id="is_confidential"
                                   {{ old('is_confidential') ? 'checked' : '' }}
                                   class="mt-0.5 h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-400/30 dark:border-slate-600 dark:bg-slate-800">
                            <span class="block">
                                <span class="block text-sm font-medium text-slate-800 dark:text-slate-100">Documento confidencial</span>
                                <span class="mt-1 block text-xs text-slate-600 dark:text-slate-400">Marca esta opción si contiene información sensible o restringida.</span>
                            </span>
                        </label>
                    </div>
                </div>

                @if(Auth::user()?->hasRole('receptionist'))
                    <div class="rounded-xl border border-sky-200 bg-gradient-to-br from-sky-50 to-indigo-50 p-6 shadow-sm dark:border-sky-900/40 dark:from-slate-900 dark:to-slate-900">
                        <div class="mb-5">
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Recibido / Acceso al Portal</h2>
                            <p class="text-sm text-slate-600 dark:text-slate-400">Se aplicará a todos los documentos del lote. Luego podemos extender a receptor por fila.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label for="recipient_name" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Nombre receptor <span class="text-rose-500">*</span></label>
                                <input type="text" name="recipient_name" id="recipient_name" value="{{ old('recipient_name') }}"
                                       class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                @error('recipient_name')
                                    <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="recipient_email" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Correo receptor <span class="text-rose-500">*</span></label>
                                <input type="email" name="recipient_email" id="recipient_email" value="{{ old('recipient_email') }}"
                                       class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                @error('recipient_email')
                                    <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label for="recipient_phone" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Teléfono receptor</label>
                                <input type="text" name="recipient_phone" id="recipient_phone" value="{{ old('recipient_phone') }}"
                                       class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                @error('recipient_phone')
                                    <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div x-show="currentStep === 4" x-cloak x-ref="step4" class="space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="mb-5">
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">Revisión</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Confirma los datos de la carga antes de crear los documentos.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/40">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Archivos a crear</h3>
                            <div class="mt-3 space-y-2 text-sm text-slate-700 dark:text-slate-300">
                                <template x-if="rows.length === 0">
                                    <p>1 documento (modo archivo único / sin lote)</p>
                                </template>
                                <template x-if="rows.length > 0">
                                    <div class="space-y-2">
                                        <template x-for="(row, index) in rows" :key="row.uid">
                                            <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs dark:border-slate-700 dark:bg-slate-900">
                                                <span class="truncate pr-3" x-text="row.title || row.fileName"></span>
                                                <span class="text-slate-500" x-text="row.sizeLabel"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/40">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Metadatos del lote (por defecto)</h3>
                            <dl class="mt-3 grid grid-cols-[110px_1fr] gap-y-2 text-sm">
                                <dt class="text-slate-500 dark:text-slate-400">Categoría</dt>
                                <dd class="text-slate-900 dark:text-white" x-text="selectedText('#category_id')"></dd>
                                <dt class="text-slate-500 dark:text-slate-400">Estado</dt>
                                <dd class="text-slate-900 dark:text-white" x-text="selectedText('#status_id')"></dd>
                                <dt class="text-slate-500 dark:text-slate-400">Prioridad</dt>
                                <dd class="text-slate-900 dark:text-white" x-text="selectedText('#priority')"></dd>
                                <dt class="text-slate-500 dark:text-slate-400">Confidencial</dt>
                                <dd class="text-slate-900 dark:text-white" x-text="isChecked('#is_confidential') ? 'Sí' : 'No'"></dd>
                            </dl>
                            <p x-show="rows.length > 0" x-cloak class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                                Los ajustes por archivo (categoría/estado) sobrescriben estos valores por defecto cuando se configuran en el paso 2.
                            </p>
                        </div>
                    </div>

                    <input type="hidden" name="title" :disabled="rows.length === 0" :value="rows.length > 0 ? (rows[0]?.title || '') : ''">
                </div>
            </div>

            <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <button type="button"
                        @click="prevStep()"
                        :disabled="currentStep === 1"
                        class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition"
                        :class="currentStep === 1 ? 'cursor-not-allowed text-slate-400' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800'">
                    ← Atrás
                </button>

                <div class="flex items-center gap-3">
                    <span x-show="isUploading" x-cloak class="text-xs font-medium text-sky-600 dark:text-sky-300">Subiendo archivos...</span>
                    <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400" x-text="`Paso ${currentStep} de 4`"></span>

                    <button type="button"
                            x-show="currentStep < 4"
                            @click="nextStep()"
                            :disabled="isUploading || hasUploadingRows()"
                            class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                        Continuar
                        <span>→</span>
                    </button>

                    <button type="submit"
                            x-show="currentStep === 4"
                            :disabled="isUploading || hasUploadingRows()"
                            class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-sky-500 to-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-900/20 transition hover:from-sky-400 hover:to-indigo-500">
                        <span x-text="rows.length > 1 ? 'Crear Documentos' : 'Crear Documento'"></span>
                    </button>
                </div>
            </div>
        </section>
    </div>
</form>

<script>
    window.uploadDocumentWizard = function () {
        return {
            currentStep: 1,
            rows: [],
            categoryOptions: @js($categoryOptions),
            statusOptions: @js($statusOptions),
            isDragging: false,
            goToStep(step) {
                const targetStep = Number(step);

                if (Number.isNaN(targetStep)) {
                    return;
                }

                if (targetStep > this.currentStep) {
                    for (let stepIndex = this.currentStep; stepIndex < targetStep; stepIndex++) {
                        if (!this.validateStep(stepIndex)) {
                            return;
                        }
                    }
                }

                this.currentStep = Math.max(1, Math.min(4, targetStep));
            },
            nextStep() {
                if (!this.validateStep(this.currentStep)) {
                    return;
                }
                this.currentStep = Math.min(4, this.currentStep + 1);
                this.persistDraftSilently();
            },
            prevStep() {
                this.currentStep = Math.max(1, this.currentStep - 1);
                this.persistDraftSilently();
            },
            hasAtLeastOneFile() {
                const hasBulk = this.rows.length > 0;
                const single = this.$refs.singleFile?.files?.length > 0;
                return hasBulk || single;
            },
            validateStep(step) {
                const stepNumber = Number(step);

                if (stepNumber === 1) {
                    if (!this.hasAtLeastOneFile()) {
                        alert('Debes seleccionar al menos un archivo para continuar.');
                        return false;
                    }

                    if (this.isUploading || this.hasUploadingRows()) {
                        alert('Espera a que termine la carga de los archivos antes de continuar.');
                        return false;
                    }

                    if (this.rows.some((row) => row.uploadStatus === 'error')) {
                        alert('Hay archivos con error de carga. Corrige o elimina esos archivos antes de continuar.');
                        return false;
                    }

                    const hasEmptyTitle = this.rows.some((row) => !String(row.title ?? '').trim());
                    if (hasEmptyTitle) {
                        alert('Todos los archivos del lote deben tener título antes de continuar.');
                        return false;
                    }
                }

                if (stepNumber === 2) {
                    const titleInput = document.getElementById('title');
                    if (this.rows.length === 0 && titleInput && !titleInput.value.trim()) {
                        titleInput.reportValidity();
                        return false;
                    }

                    const category = document.getElementById('category_id');
                    const status = document.getElementById('status_id');

                    if (category && !category.value) {
                        category.reportValidity();
                        return false;
                    }

                    if (status && !status.value) {
                        status.reportValidity();
                        return false;
                    }
                }

                if (stepNumber === 3) {
                    const priority = document.getElementById('priority');

                    if (priority && !priority.value) {
                        priority.reportValidity();
                        return false;
                    }

                    const recipientName = document.getElementById('recipient_name');
                    const recipientEmail = document.getElementById('recipient_email');

                    if (recipientName && recipientEmail) {
                        if (!recipientName.value.trim()) {
                            recipientName.reportValidity();
                            return false;
                        }

                        if (!recipientEmail.value.trim()) {
                            recipientEmail.reportValidity();
                            return false;
                        }
                    }
                }

                return true;
            },
            validateWizardBeforeSubmit() {
                if (this.isUploading || this.hasUploadingRows()) {
                    alert('Espera a que termine la carga de archivos antes de crear los documentos.');
                    return false;
                }

                for (let stepIndex = 1; stepIndex <= 3; stepIndex++) {
                    if (!this.validateStep(stepIndex)) {
                        this.currentStep = stepIndex;
                        return false;
                    }
                }

                return true;
            },
            submitForm(event) {
                if (!this.validateWizardBeforeSubmit()) {
                    return;
                }

                event.target.submit();
            },
            handleBulkSelection(event) {
                const files = Array.from(event.target.files ?? []);
                if (files.length === 0) {
                    return;
                }

                this.uploadFiles(files, true);
            },
            handleSingleSelection(event) {
                const file = event.target.files?.[0];
                if (!file) {
                    return;
                }
                this.uploadFiles([file], false);
            },
            handleDrop(event) {
                this.isDragging = false;
                const files = Array.from(event.dataTransfer?.files ?? []);
                if (!files.length) {
                    return;
                }
                this.uploadFiles(files, true);
            },
            clearRows() {
                const rows = [...this.rows];
                this.rows = [];
                if (this.$refs.bulkFiles) {
                    this.$refs.bulkFiles.value = '';
                }
                if (this.$refs.singleFile) {
                    this.$refs.singleFile.value = '';
                }
                rows.forEach((row) => {
                    if (row.id) {
                        this.deleteDraftItem(row.id);
                    }
                });
                this.persistDraftSilently();
            },
            removeRow(index) {
                const removed = this.rows.splice(index, 1)[0];
                if (removed?.id) {
                    this.deleteDraftItem(removed.id);
                }
                this.persistDraftSilently();
            },
            clearRowMetadata(index) {
                if (!this.rows[index]) {
                    return;
                }
                this.rows[index].category_id = '';
                this.rows[index].status_id = '';
            },
            makeRow(file) {
                const fileName = file.name ?? 'archivo';
                const title = this.makeTitleFromFilename(fileName);
                const extension = fileName.includes('.') ? fileName.split('.').pop().toLowerCase() : '';

                return {
                    id: null,
                    uid: `${fileName}-${file.size}-${crypto.randomUUID ? crypto.randomUUID() : Math.random().toString(36).slice(2)}`,
                    file,
                    fileName,
                    title,
                    category_id: '',
                    status_id: '',
                    extension: extension || '',
                    sizeLabel: this.formatBytes(file.size ?? 0),
                    uploadStatus: 'uploading',
                    progress: 0,
                    uploadError: '',
                };
            },
            makeTitleFromFilename(fileName) {
                const baseName = fileName.replace(/\.[^/.]+$/, '');
                const normalized = baseName.replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim();
                if (!normalized) {
                    return 'Documento';
                }
                return normalized.split(' ').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
            },
            formatBytes(bytes) {
                if (bytes < 1024) return `${bytes} B`;
                if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
                return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
            },
            selectedText(selector) {
                const el = document.querySelector(selector);
                if (!el || !el.selectedOptions || !el.selectedOptions.length) {
                    return 'Sin seleccionar';
                }
                return el.selectedOptions[0].text;
            },
            fileIconUiMap: @js($extensionUiMap),
            fileIconMeta(extension) {
                const ext = String(extension || '').toLowerCase();
                const item = this.fileIconUiMap[ext] ?? this.fileIconUiMap.unknown;
                return {
                    bgClass: item.bgClass,
                    badgeClass: item.badgeClass,
                };
            },
            fileIconSvg(extension, sizeClass = 'h-5 w-5') {
                const ext = String(extension || '').toLowerCase();
                const raw = (this.fileIconUiMap[ext] ?? this.fileIconUiMap.unknown).svg;
                return raw.replace(/class=\"([^\"]*)\"/, `class=\"${sizeClass}\"`);
            },
            fileTypeLabel(extension) {
                const ext = String(extension || '').toLowerCase();
                const item = this.fileIconUiMap[ext] ?? this.fileIconUiMap.unknown;
                return item.label || (ext.toUpperCase() || 'ARCHIVO');
            },
            hasUploadingRows() {
                return this.rows.some((row) => row.uploadStatus === 'uploading');
            },
            refreshUploadingState() {
                this.isUploading = this.hasUploadingRows();
            },
            isChecked(selector) {
                const el = document.querySelector(selector);
                return !!el?.checked;
            },
            draftId: @js(data_get($uploadDraftPayload ?? null, 'id')),
            preloadedDraft: @js($uploadDraftPayload ?? null),
            isUploading: false,
            isSavingDraft: false,
            draftMessage: '',
            uploadTempUrl: @js(route('documents.upload-drafts.temp-file')),
            saveDraftUrl: @js(route('documents.upload-drafts.save')),
            draftItemDeleteUrlTemplate: @js(route('documents.upload-drafts.items.destroy', ['draft' => '__DRAFT__', 'item' => '__ITEM__'])),
            init() {
                const previousBulkRows = Array.from(document.querySelectorAll('input[name^="bulk_items["][name$="[title]"]'));
                if (previousBulkRows.length > 0) {
                    this.currentStep = 2;
                }

                if (this.preloadedDraft) {
                    this.applyDraftToForm(this.preloadedDraft);
                }
                this.updateDraftUrl();
            },
            csrfToken() {
                return document.querySelector('input[name=\"_token\"]')?.value ?? '';
            },
            uploadFileToDraft(file, row) {
                return new Promise((resolve, reject) => {
                    const formData = new FormData();
                    formData.append('_token', this.csrfToken());
                    formData.append('file', file);
                    if (this.draftId) {
                        formData.append('draft_id', String(this.draftId));
                    }

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', this.uploadTempUrl, true);
                    xhr.timeout = 45000;
                    xhr.withCredentials = true;
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                    xhr.upload.addEventListener('progress', (event) => {
                        if (!event.lengthComputable) {
                            return;
                        }
                        row.progress = Math.min(99, Math.round((event.loaded / event.total) * 100));
                    });

                    xhr.onload = () => {
                        if (xhr.status < 200 || xhr.status >= 300) {
                            reject(new Error('No se pudo cargar uno de los archivos.'));
                            return;
                        }

                        try {
                            const payload = JSON.parse(xhr.responseText);
                            resolve(payload);
                        } catch (error) {
                            reject(new Error('Respuesta inválida al cargar archivo.'));
                        }
                    };

                    xhr.onerror = () => reject(new Error('Error de red durante la carga del archivo.'));
                    xhr.onabort = () => reject(new Error('La carga del archivo fue cancelada.'));
                    xhr.ontimeout = () => reject(new Error('La carga del archivo tardó demasiado. Intenta de nuevo.'));
                    xhr.send(formData);
                });
            },
            async uploadFileWithRetry(file, row, maxAttempts = 2) {
                let lastError = null;

                for (let attempt = 1; attempt <= maxAttempts; attempt++) {
                    try {
                        if (attempt > 1) {
                            row.uploadError = '';
                            row.uploadStatus = 'uploading';
                            row.progress = 0;
                        }

                        return await this.uploadFileToDraft(file, row);
                    } catch (error) {
                        lastError = error;
                        row.uploadError = error?.message || 'Error al cargar archivo.';

                        if (attempt < maxAttempts) {
                            await new Promise((resolve) => setTimeout(resolve, 500));
                        }
                    }
                }

                throw lastError ?? new Error('No se pudo cargar el archivo.');
            },
            async uploadFiles(files, append = true) {
                if (this.isUploading || this.hasUploadingRows()) {
                    alert('Ya hay una carga en progreso. Espera a que termine antes de agregar más archivos.');
                    return;
                }

                if (!append) {
                    const existing = [...this.rows];
                    this.rows = [];
                    existing.forEach((row) => {
                        if (row.id) {
                            this.deleteDraftItem(row.id);
                        }
                    });
                }

                this.isUploading = true;
                this.draftMessage = '';

                try {
                    for (const file of files) {
                        const row = this.makeRow(file);
                        this.rows.push(row);

                        const payload = await this.uploadFileWithRetry(file, row);
                        this.draftId = payload.draft_id;
                        this.updateDraftUrl();

                        const uploadedRow = payload.item ?? {};
                        Object.assign(row, uploadedRow, {
                            uploadStatus: 'ready',
                            progress: 100,
                            uploadError: '',
                        });
                        this.refreshUploadingState();
                    }

                    if (this.$refs.bulkFiles) {
                        this.$refs.bulkFiles.value = '';
                    }
                    if (this.$refs.singleFile) {
                        this.$refs.singleFile.value = '';
                    }

                    const titleInput = document.getElementById('title');
                    if (files.length === 1 && this.rows.length === 1 && titleInput && !titleInput.value.trim()) {
                        titleInput.value = this.rows[0].title ?? this.makeTitleFromFilename(files[0].name);
                    }
                } catch (error) {
                    const pendingRow = [...this.rows].reverse().find((candidate) => candidate.uploadStatus === 'uploading');
                    if (pendingRow) {
                        pendingRow.uploadStatus = 'error';
                        pendingRow.progress = 0;
                        pendingRow.uploadError = error?.message || 'Error al cargar archivo.';
                    }
                    this.refreshUploadingState();
                    alert(error?.message || 'Error al cargar archivos.');
                } finally {
                    this.refreshUploadingState();
                    this.persistDraftSilently();
                }
            },
            collectDraftPayload() {
                const categoryInput = document.getElementById('category_id');
                const statusInput = document.getElementById('status_id');
                const priorityInput = document.getElementById('priority');

                return {
                    draft_id: this.draftId,
                    current_step: this.currentStep,
                    title: document.getElementById('title')?.value || null,
                    description: document.getElementById('description')?.value || null,
                    category_id: categoryInput?.value || null,
                    status_id: statusInput?.value || null,
                    priority: priorityInput?.value || null,
                    is_confidential: this.isChecked('#is_confidential'),
                    recipient_name: document.getElementById('recipient_name')?.value || null,
                    recipient_email: document.getElementById('recipient_email')?.value || null,
                    recipient_phone: document.getElementById('recipient_phone')?.value || null,
                    items: this.rows.map((row, index) => ({
                        id: row.id || null,
                        title: row.title || null,
                        category_id: row.category_id || null,
                        status_id: row.status_id || null,
                        sort_order: index + 1,
                    })),
                };
            },
            async persistDraft(showMessage = false) {
                if (!this.draftId && this.rows.length === 0) {
                    if (showMessage) {
                        alert('Primero selecciona al menos un archivo para guardar un borrador.');
                    }
                    return false;
                }

                this.isSavingDraft = true;
                this.draftMessage = '';

                try {
                    const response = await fetch(this.saveDraftUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(this.collectDraftPayload()),
                    });

                    if (!response.ok) {
                        throw new Error('No se pudo guardar el borrador.');
                    }

                    const payload = await response.json();
                    this.draftId = payload.draft_id;
                    this.updateDraftUrl();

                    if (payload.draft) {
                        this.applyDraftToForm(payload.draft, false);
                    }

                    if (showMessage) {
                        this.draftMessage = 'Borrador guardado correctamente.';
                        setTimeout(() => {
                            this.draftMessage = '';
                        }, 3000);
                    }

                    return true;
                } catch (error) {
                    if (showMessage) {
                        alert(error?.message || 'No se pudo guardar el borrador.');
                    }
                    return false;
                } finally {
                    this.isSavingDraft = false;
                }
            },
            saveDraft() {
                this.persistDraft(true);
            },
            persistDraftSilently() {
                if (!this.draftId && this.rows.length === 0) {
                    return;
                }

                this.persistDraft(false);
            },
            async deleteDraftItem(itemId) {
                if (!this.draftId || !itemId) {
                    return;
                }

                const url = this.draftItemDeleteUrlTemplate
                    .replace('__DRAFT__', String(this.draftId))
                    .replace('__ITEM__', String(itemId));

                await fetch(url, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }).catch(() => null);
            },
            applyDraftToForm(draft, replaceRows = true) {
                if (!draft) {
                    return;
                }

                this.draftId = draft.id ?? this.draftId;
                this.updateDraftUrl();
                this.currentStep = draft.current_step ?? this.currentStep;

                if (replaceRows && Array.isArray(draft.items)) {
                    this.rows = draft.items.map((item) => ({
                        ...item,
                        file: null,
                        uploadStatus: 'ready',
                        progress: 100,
                        uploadError: '',
                    }));
                }

                const setValue = (id, value) => {
                    const el = document.getElementById(id);
                    if (el && value !== undefined && value !== null) {
                        if (el.type === 'checkbox') {
                            el.checked = !!value;
                        } else {
                            el.value = String(value);
                        }
                    }
                };

                setValue('title', draft.title);
                setValue('description', draft.description);
                setValue('category_id', draft.category_id);
                setValue('status_id', draft.status_id);
                setValue('priority', draft.priority);
                setValue('is_confidential', !!draft.is_confidential);
                setValue('recipient_name', draft.recipient_name);
                setValue('recipient_email', draft.recipient_email);
                setValue('recipient_phone', draft.recipient_phone);
            },
            updateDraftUrl() {
                if (!window.history?.replaceState) {
                    return;
                }

                const url = new URL(window.location.href);

                if (this.draftId) {
                    url.searchParams.set('draft', String(this.draftId));
                } else {
                    url.searchParams.delete('draft');
                }

                window.history.replaceState({}, '', url.toString());
            },
        };
    };
</script>
@endsection
