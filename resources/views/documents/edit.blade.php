@extends('layouts.app')

@section('title', 'Editar Documento')

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

    $hasAttachment = filled($document->file_path);
@endphp

<div class="space-y-6">
    <section class="rounded-2xl border border-white/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-600 dark:text-sky-400">Documentos</p>
                <h1 class="mt-1 text-3xl font-black tracking-tight text-slate-900 dark:text-white">Editar Documento</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Actualiza metadatos, prioridad y archivo adjunto manteniendo un flujo visual consistente con el portal.
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('documents.show', $document) }}"
                   class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                    Volver al detalle
                </a>
                @if($hasAttachment)
                    <a href="{{ route('documents.download', $document) }}"
                       class="inline-flex h-10 items-center justify-center rounded-lg border border-sky-300/20 bg-gradient-to-r from-sky-500 to-indigo-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:from-sky-400 hover:to-indigo-500">
                        Descargar adjunto
                    </a>
                @endif
            </div>
        </div>
    </section>

    <form action="{{ route('documents.update', $document) }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        @csrf
        @method('PUT')

        <div class="space-y-6">
            <section class="rounded-2xl border border-white/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-5">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Información principal</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Datos descriptivos y clasificación del documento.</p>
                </div>

                <div class="space-y-5">
                    <div>
                        <label for="title" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">
                            Título <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="title"
                            id="title"
                            value="{{ old('title', $document->title) }}"
                            required
                            class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            placeholder="Ej. Contrato de mantenimiento sede norte"
                        >
                        @error('title')
                            <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="description" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Descripción</label>
                        <textarea name="description" id="description" rows="4"
                                  class="block w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                  placeholder="Contexto, contenido o notas relevantes del documento...">{{ old('description', $document->description) }}</textarea>
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
                                    <option value="{{ $category->id }}" @selected(old('category_id', $document->category_id) == $category->id)>
                                        {{ $translateName($category, 'Categoría') }}
                                    </option>
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
                                    <option value="{{ $status->id }}" @selected(old('status_id', $document->status_id) == $status->id)>
                                        {{ $translateName($status, 'Estado') }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status_id')
                                <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="priority" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">Prioridad <span class="text-rose-500">*</span></label>
                        <select name="priority" id="priority" required
                                class="block h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                            <option value="low" @selected(old('priority', $document->priority?->value ?? $document->priority) == 'low')>Baja</option>
                            <option value="medium" @selected(old('priority', $document->priority?->value ?? $document->priority) == 'medium')>Media</option>
                            <option value="high" @selected(old('priority', $document->priority?->value ?? $document->priority) == 'high')>Alta</option>
                            <option value="urgent" @selected(old('priority', $document->priority?->value ?? $document->priority) == 'urgent')>Urgente</option>
                        </select>
                        @error('priority')
                            <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-white/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-5">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Adjunto</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Reemplaza el archivo actual si es necesario. El sistema mantendrá el documento y actualizará el adjunto.</p>
                </div>

                @if($hasAttachment)
                    <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/40">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300">📎</div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">Archivo actual adjunto</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ basename((string) $document->file_path) }}</p>
                                </div>
                            </div>
                            <a href="{{ route('documents.download', $document) }}" class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                Descargar
                            </a>
                        </div>
                    </div>
                @endif

                <label for="file" class="block">
                    <span class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-200">
                        {{ $hasAttachment ? 'Reemplazar archivo adjunto' : 'Archivo adjunto' }}
                    </span>
                    <div class="rounded-xl border-2 border-dashed border-sky-300/40 bg-slate-50/70 p-4 transition dark:border-sky-700/40 dark:bg-slate-950/30">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">Selecciona un archivo para cargar</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG (máx. 10MB)</p>
                                @if($hasAttachment)
                                    <p class="mt-1 text-xs text-amber-600 dark:text-amber-300">Si seleccionas un archivo nuevo, reemplazará el adjunto actual.</p>
                                @endif
                            </div>
                            <span class="inline-flex h-9 items-center justify-center rounded-lg border border-sky-300/20 bg-gradient-to-r from-sky-500 to-indigo-600 px-3 text-xs font-semibold text-white">
                                Elegir archivo
                            </span>
                        </div>
                        <input type="file"
                               name="file"
                               id="file"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                               class="mt-4 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700 hover:file:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:file:bg-slate-700 dark:file:text-slate-200">
                    </div>
                </label>
                @error('file')
                    <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-300">{{ $message }}</p>
                @enderror
            </section>
        </div>

        <aside class="space-y-6">
            <section class="rounded-2xl border border-white/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-bold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Opciones</h2>

                <label class="mt-4 flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/40">
                    <input type="checkbox"
                           name="is_confidential"
                           id="is_confidential"
                           {{ old('is_confidential', $document->is_confidential) ? 'checked' : '' }}
                           class="mt-0.5 h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-400/30 dark:border-slate-600 dark:bg-slate-800">
                    <span class="block">
                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-100">Documento confidencial</span>
                        <span class="mt-1 block text-xs text-slate-600 dark:text-slate-400">Restringe visibilidad y manejo si contiene información sensible.</span>
                    </span>
                </label>

                <div class="mt-5 space-y-3">
                    <button type="submit"
                            class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-gradient-to-r from-sky-500 to-indigo-600 px-4 text-sm font-semibold text-white shadow-lg shadow-indigo-900/20 transition hover:from-sky-400 hover:to-indigo-500">
                        Actualizar Documento
                    </button>
                    <a href="{{ route('documents.show', $document) }}"
                       class="inline-flex h-11 w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                        Cancelar
                    </a>
                </div>
            </section>

            <section class="rounded-2xl border border-white/70 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-bold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Resumen</h2>
                <dl class="mt-4 grid grid-cols-[110px_1fr] gap-y-3 text-sm">
                    <dt class="text-slate-500 dark:text-slate-400">ID</dt>
                    <dd class="font-medium text-slate-900 dark:text-white">#{{ $document->id }}</dd>
                    <dt class="text-slate-500 dark:text-slate-400">Número</dt>
                    <dd class="font-medium text-slate-900 dark:text-white">{{ $document->document_number ?: 'Sin número' }}</dd>
                    <dt class="text-slate-500 dark:text-slate-400">Creado</dt>
                    <dd class="font-medium text-slate-900 dark:text-white">{{ $document->created_at?->format('d/m/Y H:i') }}</dd>
                    <dt class="text-slate-500 dark:text-slate-400">Actualizado</dt>
                    <dd class="font-medium text-slate-900 dark:text-white">{{ $document->updated_at?->format('d/m/Y H:i') }}</dd>
                </dl>
            </section>
        </aside>
    </form>
</div>
@endsection
