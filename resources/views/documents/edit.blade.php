@extends('layouts.app')

@section('title', 'Editar Documento')

@section('content')
<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6">
        <div class="mb-6">
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                Editar Documento
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Actualiza los campos necesarios del documento.
            </p>
        </div>

        <form action="{{ route('documents.update', $document) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Título -->
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Título <span class="text-red-600">*</span>
                </label>
                <input type="text"
                       name="title"
                       id="title"
                       value="{{ old('title', $document->title) }}"
                       required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 sm:text-sm">
                @error('title')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Descripción -->
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Descripción
                </label>
                <textarea name="description"
                          id="description"
                          rows="4"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 sm:text-sm">{{ old('description', $document->description) }}</textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Categoría y Estado -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Categoría <span class="text-red-600">*</span>
                    </label>
                    <select name="category_id"
                            id="category_id"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 sm:text-sm">
                        <option value="">Seleccionar categoría</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}"
                                    {{ old('category_id', $document->category_id) == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('category_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="status_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Estado <span class="text-red-600">*</span>
                    </label>
                    <select name="status_id"
                            id="status_id"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 sm:text-sm">
                        <option value="">Seleccionar estado</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->id }}"
                                    {{ old('status_id', $document->status_id) == $status->id ? 'selected' : '' }}>
                                {{ $status->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('status_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Prioridad -->
            <div>
                <label for="priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Prioridad <span class="text-red-600">*</span>
                </label>
                <select name="priority"
                        id="priority"
                        required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100 sm:text-sm">
                    <option value="low" {{ old('priority', $document->priority) == 'low' ? 'selected' : '' }}>Baja</option>
                    <option value="medium" {{ old('priority', $document->priority) == 'medium' ? 'selected' : '' }}>Media</option>
                    <option value="high" {{ old('priority', $document->priority) == 'high' ? 'selected' : '' }}>Alta</option>
                </select>
                @error('priority')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Archivo adjunto actual -->
            @if($document->file)
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-md">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                Archivo actual adjunto
                            </span>
                        </div>
                        <a href="{{ route('documents.download', $document) }}"
                           class="text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                            Descargar
                        </a>
                    </div>
                </div>
            @endif

            <!-- Nuevo archivo adjunto -->
            <div>
                <label for="file" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ $document->file ? 'Reemplazar archivo adjunto' : 'Archivo adjunto' }}
                </label>
                <input type="file"
                       name="file"
                       id="file"
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                       class="mt-1 block w-full text-sm text-gray-500 dark:text-gray-400
                              file:mr-4 file:py-2 file:px-4
                              file:rounded-md file:border-0
                              file:text-sm file:font-semibold
                              file:bg-indigo-50 file:text-indigo-700
                              hover:file:bg-indigo-100
                              dark:file:bg-indigo-900 dark:file:text-indigo-300">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Formatos permitidos: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG (Máx. 10MB)
                    @if($document->file)
                        <br>Si seleccionas un nuevo archivo, reemplazará el archivo actual.
                    @endif
                </p>
                @error('file')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Confidencial -->
            <div class="flex items-start">
                <div class="flex items-center h-5">
                    <input type="checkbox"
                           name="is_confidential"
                           id="is_confidential"
                           {{ old('is_confidential', $document->is_confidential) ? 'checked' : '' }}
                           class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600">
                </div>
                <div class="ml-3 text-sm">
                    <label for="is_confidential" class="font-medium text-gray-700 dark:text-gray-300">
                        Documento confidencial
                    </label>
                    <p class="text-gray-500 dark:text-gray-400">
                        Marca esta opción si el documento contiene información sensible.
                    </p>
                </div>
            </div>

            <!-- Botones -->
            <div class="flex justify-end space-x-3 pt-4">
                <a href="{{ route('documents.show', $document) }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    Cancelar
                </a>
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    Actualizar Documento
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
