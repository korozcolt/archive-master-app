@extends('layouts.app')

@section('title', 'Carga Historica')

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
            $structured = (array) $location->structured_data;

            return [
                'id' => (int) $location->id,
                'code' => (string) ($location->code ?? ''),
                'path' => (string) $location->full_path,
                'shelf' => (string) data_get($structured, 'estante', ''),
                'bay' => (string) (data_get($structured, 'entrepaño') ?? data_get($structured, 'entrepano') ?? ''),
                'box' => (string) data_get($structured, 'caja', ''),
                'capacity_used' => (int) ($location->capacity_used ?? 0),
                'capacity_total' => $location->capacity_total,
            ];
        })
        ->filter(fn (array $location): bool => $location['box'] !== '')
        ->values();

    $documentaryTypeOptions = $documentaryTypes
        ->map(fn ($type) => [
            'id' => (int) $type->id,
            'label' => trim(sprintf(
                '%s - [%s / %s] %s',
                $type->code,
                $type->subseries?->series?->name ?? 'Sin Serie',
                $type->subseries?->name ?? 'Sin Subserie',
                $type->name,
            )),
            'series' => (string) ($type->subseries?->series?->name ?? ''),
            'subseries' => (string) ($type->subseries?->name ?? ''),
            'sort_key' => trim(sprintf(
                '%s - %s - %s',
                $type->subseries?->series?->name ?? '',
                $type->subseries?->name ?? '',
                $type->name
            )),
        ])
        ->sortBy('sort_key')
        ->values();

    $categoryOptions = $categories
        ->map(fn ($category) => [
            'id' => (int) $category->id,
            'label' => $translateName($category, 'Categoría'),
        ])
        ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
        ->values();

    $rememberedLocationId = old('physical_location_id', $rememberedPhysicalLocationId);
@endphp

<div
    class="space-y-6"
    x-data="{
        locations: @js($locationOptions),
        selectedShelf: '',
        selectedBay: '',
        selectedLocationId: @js($rememberedLocationId ? (string) $rememberedLocationId : ''),
        defaultDocumentaryTypeId: @js((string) old('documentary_type_id', '')),
        rows: [
            {
                uid: 'row-' + Date.now() + '-' + Math.random().toString(36).slice(2, 11),
                fileName: '',
                folder: '',
                volume: '',
                reference_code: '',
                year: '',
                description: '',
                documentary_type_id: @js((string) old('documentary_type_id', '')),
            },
        ],
        init() {
            const remembered = this.locations.find((location) => String(location.id) === String(this.selectedLocationId));

            if (remembered) {
                this.selectedShelf = remembered.shelf;
                this.selectedBay = remembered.bay;
            }
        },
        shelves() {
            return [...new Set(this.locations.map((location) => location.shelf).filter(Boolean))].sort((a, b) => String(a).localeCompare(String(b), undefined, { numeric: true }));
        },
        bays() {
            return [...new Set(this.locations.filter((location) => location.shelf === this.selectedShelf).map((location) => location.bay).filter(Boolean))].sort((a, b) => String(a).localeCompare(String(b), undefined, { numeric: true }));
        },
        boxes() {
            return this.locations
                .filter((location) => location.shelf === this.selectedShelf && location.bay === this.selectedBay)
                .sort((a, b) => String(a.box).localeCompare(String(b.box), undefined, { numeric: true }));
        },
        selectedLocation() {
            return this.locations.find((location) => String(location.id) === String(this.selectedLocationId)) || null;
        },
        selectShelf(value) {
            this.selectedShelf = value;
            this.selectedBay = '';
            this.selectedLocationId = '';
        },
        selectBay(value) {
            this.selectedBay = value;
            this.selectedLocationId = '';
        },
        addRow() {
            this.rows.push({
                uid: 'row-' + Date.now() + '-' + Math.random().toString(36).slice(2, 11),
                fileName: '',
                folder: '',
                volume: '',
                reference_code: '',
                year: '',
                description: '',
                documentary_type_id: this.defaultDocumentaryTypeId,
            });
        },
        removeRow(index) {
            if (this.rows.length === 1) {
                return;
            }

            this.rows.splice(index, 1);
        },
        setDefaultDocumentaryType(value) {
            this.defaultDocumentaryTypeId = value;
            this.rows = this.rows.map((row) => ({
                ...row,
                documentary_type_id: row.documentary_type_id || value,
            }));
        },
        handleBulkFiles(filesList) {
            const files = Array.from(filesList);
            if (files.length === 0) {
                return;
            }

            if (this.rows.length === 1 && !this.rows[0].fileName) {
                this.rows = [];
            }

            const startIndex = this.rows.length;

            files.forEach((file, i) => {
                const uid = 'row-' + Date.now() + '-' + Math.random().toString(36).slice(2, 11) + '-' + i;
                this.rows.push({
                    uid: uid,
                    fileName: file.name,
                    folder: '',
                    volume: '',
                    reference_code: '',
                    year: '',
                    description: '',
                    documentary_type_id: this.defaultDocumentaryTypeId,
                });

                this.$nextTick(() => {
                    const index = startIndex + i;
                    const inputs = document.getElementsByName(`rows[${index}][file]`);
                    if (inputs && inputs[0]) {
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        inputs[0].files = dt.files;
                    }
                });
            });
        },
        hasMissingDocumentaryType() {
            return this.rows.some((row) => !row.documentary_type_id);
        },
    }"
>
    <section class="border border-slate-800 bg-slate-900 p-6 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="flex flex-wrap gap-2">
                    <span class="inline-flex items-center border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-amber-200">
                        Archivo Central
                    </span>
                    <span class="inline-flex items-center border border-sky-500/20 bg-sky-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-sky-200">
                        Carga por caja
                    </span>
                </div>
                <h1 class="mt-4 text-2xl font-semibold text-white">Carga historica por caja</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
                    Selecciona primero la ubicacion fisica final y luego registra cada carpeta o documento digitalizado dentro de esa caja.
                </p>
            </div>

            <div class="border border-slate-800 bg-slate-950 px-4 py-3 text-sm text-slate-300">
                <span class="block text-xs uppercase tracking-[0.14em] text-slate-500">Custodio</span>
                <span class="mt-1 block font-semibold text-white">{{ $translateName($centralArchiveDepartment, 'Archivo Central') }}</span>
            </div>
        </div>
    </section>

    @if ($errors->any())
        <div class="border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-100">
            <p class="font-semibold">Revisa los datos antes de continuar.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('documents.historical.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <section class="border border-slate-800 bg-slate-900 p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-white">1. Ubicacion fisica</h2>
            <p class="mt-1 text-sm text-slate-400">El flujo sigue la forma de trabajo del archivo: estante, entrepano y caja.</p>

            <select
                name="physical_location_id"
                x-model="selectedLocationId"
                required
                aria-hidden="true"
                tabindex="-1"
                class="absolute h-px w-px overflow-hidden opacity-0"
            >
                <option value="">Seleccionar caja</option>
                <template x-for="location in locations" :key="location.id">
                    <option :value="String(location.id)" x-text="location.path"></option>
                </template>
            </select>

            <div class="mt-5 grid gap-4 lg:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-200">Estante</label>
                    <select
                        dusk="historical-shelf-select"
                        x-model="selectedShelf"
                        @change="selectShelf($event.target.value)"
                        class="block h-11 w-full border border-slate-700 bg-slate-800 px-3 text-sm text-white outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20"
                    >
                        <option value="">Seleccionar estante</option>
                        <template x-for="shelf in shelves()" :key="shelf">
                            <option :value="shelf" x-text="`Estante ${shelf}`"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-200">Entrepano</label>
                    <select
                        dusk="historical-bay-select"
                        x-model="selectedBay"
                        @change="selectBay($event.target.value)"
                        :disabled="!selectedShelf"
                        class="block h-11 w-full border border-slate-700 bg-slate-800 px-3 text-sm text-white outline-none disabled:opacity-50 focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20"
                    >
                        <option value="">Seleccionar entrepano</option>
                        <template x-for="bay in bays()" :key="bay">
                            <option :value="bay" x-text="`Entrepano ${bay}`"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-200">Caja</label>
                    <select
                        dusk="historical-box-select"
                        x-model="selectedLocationId"
                        :disabled="!selectedBay"
                        required
                        class="block h-11 w-full border border-slate-700 bg-slate-800 px-3 text-sm text-white outline-none disabled:opacity-50 focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20"
                    >
                        <option value="">Seleccionar caja</option>
                        <template x-for="box in boxes()" :key="box.id">
                            <option :value="String(box.id)" x-text="`Caja ${box.box} (${box.capacity_total ? `${box.capacity_used}/${box.capacity_total}` : 'sin limite'})`"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div class="mt-5 border border-slate-800 bg-slate-950 p-4 text-sm text-slate-300" x-show="selectedLocation()" x-cloak>
                <p class="font-semibold text-white" x-text="selectedLocation()?.code"></p>
                <p class="mt-1" x-text="selectedLocation()?.path"></p>
            </div>

            <div class="mt-5 border border-dashed border-slate-700 p-4 text-sm text-slate-400" x-show="locations.length === 0">
                No hay cajas activas configuradas. El administrador de archivo debe crear ubicaciones con nivel Caja.
            </div>
        </section>

        <section class="border border-slate-800 bg-slate-900 p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-white">2. Datos generales</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-200">Categoria tematica</label>
                    <div class="relative" x-data="{
                        open: false,
                        search: '',
                        options: @js($categoryOptions),
                        selectedCategoryId: @js((string) old('category_id', '')),
                        get filteredOptions() {
                            if (!this.search) return this.options;
                            const q = this.search.toLowerCase();
                            return this.options.filter(opt => opt.label.toLowerCase().includes(q));
                        },
                        selectedLabel() {
                            const found = this.options.find(opt => String(opt.id) === String(this.selectedCategoryId));
                            return found ? found.label : 'Seleccionar categoria';
                        }
                    }">
                        <!-- Trigger Button -->
                        <button
                            type="button"
                            @click="open = !open"
                            @keydown.escape="open = false; search = '';"
                            role="combobox"
                            aria-controls="category-options"
                            :aria-expanded="open.toString()"
                            aria-haspopup="listbox"
                            class="flex h-11 w-full items-center justify-between border border-slate-700 bg-slate-800 px-3 text-left text-sm text-white outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20"
                        >
                            <span x-text="selectedLabel()" class="truncate"></span>
                            <svg class="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Hidden Select for Form Submission & Browser Validation -->
                        <select
                            name="category_id"
                            id="category_id"
                            required
                            x-model="selectedCategoryId"
                            class="absolute opacity-0 w-px h-px overflow-hidden"
                            tabindex="-1"
                        >
                            <option value="">Seleccionar categoria</option>
                            @foreach ($categoryOptions as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>

                        <!-- Dropdown Panel -->
                        <div id="category-options" x-show="open" @click.away="open = false; search = '';" role="listbox" class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto border border-slate-700 bg-slate-900 p-2 shadow-lg" x-cloak>
                            <!-- Search Input -->
                            <input
                                type="text"
                                x-model="search"
                                placeholder="Buscar categoría..."
                                class="mb-2 h-9 w-full border border-slate-700 bg-slate-950 px-2 text-sm text-white outline-none focus:border-amber-400"
                            >

                            <!-- Options List -->
                            <div class="space-y-1">
                                <button type="button" role="option" @click="selectedCategoryId = ''; open = false; search = '';" class="block w-full px-2 py-1.5 text-left text-sm text-slate-400 transition hover:bg-amber-500 hover:text-slate-950">
                                    Seleccionar categoria
                                </button>
                                <template x-for="opt in filteredOptions" :key="opt.id">
                                    <button type="button" role="option" @click="selectedCategoryId = String(opt.id); open = false; search = '';" class="block w-full px-2 py-1.5 text-left text-sm text-slate-200 transition hover:bg-amber-500 hover:text-slate-950">
                                        <span x-text="opt.label"></span>
                                    </button>
                                </template>
                                <div x-show="filteredOptions.length === 0" class="px-2 py-1.5 text-sm text-slate-500">
                                    No se encontraron resultados
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="original_department_id" class="mb-1.5 block text-sm font-medium text-slate-200">Dependencia productora</label>
                    <select name="original_department_id" id="original_department_id" required class="block h-11 w-full border border-slate-700 bg-slate-800 px-3 text-sm text-white outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20">
                        <option value="">Seleccionar dependencia</option>
                        @foreach ($producerDepartments as $department)
                            <option value="{{ $department->id }}" @selected((string) old('original_department_id') === (string) $department->id)>{{ $translateName($department, 'Dependencia') }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-200">Tipo documental por defecto</label>
                    <div class="relative" x-data="{
                        open: false,
                        search: '',
                        options: @js($documentaryTypeOptions),
                        get filteredOptions() {
                            if (!this.search) return this.options;
                            const q = this.search.toLowerCase();
                            return this.options.filter(opt => opt.label.toLowerCase().includes(q));
                        },
                        selectedLabel() {
                            const found = this.options.find(opt => String(opt.id) === String(defaultDocumentaryTypeId));
                            return found ? found.label : 'Seleccionar tipo documental';
                        }
                    }">
                        <!-- Trigger Button -->
                        <button
                            type="button"
                            @click="open = !open"
                            @keydown.escape="open = false"
                            role="combobox"
                            aria-controls="default-documentary-type-options"
                            :aria-expanded="open.toString()"
                            aria-haspopup="listbox"
                            class="flex h-11 w-full items-center justify-between border border-slate-700 bg-slate-800 px-3 text-left text-sm text-white outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20"
                        >
                            <span x-text="selectedLabel()" class="truncate"></span>
                            <svg class="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Hidden Select for Form Submission -->
                        <select
                            name="documentary_type_id"
                            x-model="defaultDocumentaryTypeId"
                            class="absolute opacity-0 w-px h-px overflow-hidden"
                            tabindex="-1"
                            @change="setDefaultDocumentaryType($event.target.value)"
                        >
                            <option value="">Seleccionar tipo documental</option>
                            @foreach ($documentaryTypeOptions as $type)
                                <option value="{{ $type['id'] }}">{{ $type['label'] }}</option>
                            @endforeach
                        </select>

                        <!-- Dropdown Panel -->
                        <div id="default-documentary-type-options" x-show="open" @click.away="open = false" role="listbox" class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto border border-slate-700 bg-slate-900 p-2 shadow-lg" x-cloak>
                            <!-- Search Input -->
                            <input type="text" x-model="search" placeholder="Buscar por código, serie o tipo..." class="mb-2 h-9 w-full border border-slate-700 bg-slate-950 px-2 text-sm text-white outline-none focus:border-amber-400">

                            <!-- Options List -->
                            <div class="space-y-1">
                                <button type="button" role="option" @click="setDefaultDocumentaryType(''); open = false; search = '';" class="block w-full px-2 py-1.5 text-left text-sm text-slate-400 transition hover:bg-amber-500 hover:text-slate-950">
                                    Seleccionar tipo documental
                                </button>
                                <template x-for="opt in filteredOptions" :key="opt.id">
                                    <button type="button" role="option" @click="setDefaultDocumentaryType(String(opt.id)); open = false; search = '';" class="block w-full px-2 py-1.5 text-left text-sm text-slate-200 transition hover:bg-amber-500 hover:text-slate-950">
                                        <span x-text="opt.label"></span>
                                    </button>
                                </template>
                                <div x-show="filteredOptions.length === 0" class="px-2 py-1.5 text-sm text-slate-500">
                                    No se encontraron resultados
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="mt-1.5 text-xs text-slate-400">El sistema derivara automaticamente serie y subserie desde este tipo.</p>
                </div>

                <div>
                    <label for="access_level" class="mb-1.5 block text-sm font-medium text-slate-200">Nivel de acceso opcional</label>
                    <select name="access_level" id="access_level" class="block h-11 w-full border border-slate-700 bg-slate-800 px-3 text-sm text-white outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20">
                        <option value="">Usar valor del tipo documental</option>
                        @foreach (\App\Enums\DocumentAccessLevel::cases() as $accessLevel)
                            <option value="{{ $accessLevel->value }}" @selected(old('access_level') === $accessLevel->value)>{{ $accessLevel->getLabel() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-200">Tipo digital</label>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach (['original' => 'Original digital', 'copia' => 'Copia digital'] as $value => $label)
                            <label class="flex items-center gap-2 border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100">
                                <input type="radio" name="digital_document_type" value="{{ $value }}" @checked(old('digital_document_type', 'copia') === $value) required>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-200">Soporte fisico</label>
                    <div class="grid gap-2">
                        @foreach (['original' => 'Fisico original', 'copia' => 'Fisico copia', 'no_aplica' => 'No aplica'] as $value => $label)
                            <label class="flex items-center gap-2 border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100">
                                <input type="radio" name="physical_document_type" value="{{ $value }}" @checked(old('physical_document_type', 'original') === $value) required>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section class="border border-slate-800 bg-slate-900 p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-white">3. Carpetas y documentos de la caja</h2>
                    <p class="mt-1 text-sm text-slate-400">Cada fila crea un documento independiente dentro de la caja seleccionada.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="$refs.bulkFiles.click()" class="inline-flex h-10 items-center justify-center border border-sky-400/40 bg-sky-500/10 px-4 text-sm font-semibold text-white">
                        📄 Cargar varios archivos
                    </button>
                    <button type="button" @click="addRow()" class="inline-flex h-10 items-center justify-center border border-amber-400/40 bg-amber-500/10 px-4 text-sm font-semibold text-amber-100">
                        Agregar fila
                    </button>
                </div>
                <input type="file" x-ref="bulkFiles" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" class="hidden" @change="handleBulkFiles($event.target.files)">
            </div>

            <div class="mt-5 space-y-4">
                <template x-for="(row, index) in rows" :key="row.uid">
                    <div class="border border-slate-800 bg-slate-950 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-white" x-text="`Documento ${index + 1}`"></p>
                            <button type="button" @click="removeRow(index)" class="text-sm font-medium text-rose-300" x-show="rows.length > 1">
                                Quitar
                            </button>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-3">
                            <div class="lg:col-span-3">
                                <label class="mb-1.5 block text-sm font-medium text-slate-200">Archivo digitalizado</label>
                                <input
                                    dusk="historical-row-file"
                                    type="file"
                                    :name="`rows[${index}][file]`"
                                    required
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                    @change="row.fileName = $event.target.files[0]?.name || ''"
                                    class="block w-full border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white file:mr-4 file:border-0 file:bg-slate-700 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white"
                                >
                                <p class="mt-1 text-xs text-slate-400" x-show="row.fileName" x-text="row.fileName"></p>
                            </div>

                            <div class="lg:col-span-3">
                                <label class="mb-1.5 block text-sm font-medium text-slate-200">Tipo documental</label>
                                <div class="relative" x-data="{
                                    open: false,
                                    search: '',
                                    options: @js($documentaryTypeOptions),
                                    get filteredOptions() {
                                        if (!this.search) return this.options;
                                        const q = this.search.toLowerCase();
                                        return this.options.filter(opt => opt.label.toLowerCase().includes(q));
                                    },
                                    selectedLabel() {
                                        const found = this.options.find(opt => String(opt.id) === String(row.documentary_type_id));
                                        return found ? found.label : 'Seleccionar tipo documental';
                                    }
                                }">
                                    <!-- Trigger Button -->
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        @keydown.escape="open = false"
                                        role="combobox"
                                        :aria-controls="`documentary-row-options-${row.uid}`"
                                        :aria-expanded="open.toString()"
                                        aria-haspopup="listbox"
                                        class="flex h-11 w-full items-center justify-between border border-slate-700 bg-slate-800 px-3 text-left text-sm text-white outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20"
                                    >
                                        <span x-text="selectedLabel()" class="truncate"></span>
                                        <svg class="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>

                                    <!-- Hidden Select for Form Submission & Dusk Testing -->
                                    <select
                                        dusk="historical-row-type-select"
                                        :name="`rows[${index}][documentary_type_id]`"
                                        x-model="row.documentary_type_id"
                                        @change="row.documentary_type_id = $event.target.value"
                                        required
                                        class="absolute opacity-0 w-px h-px overflow-hidden"
                                        tabindex="-1"
                                    >
                                        <option value="">Seleccionar tipo documental</option>
                                        @foreach ($documentaryTypeOptions as $type)
                                            <option value="{{ $type['id'] }}">{{ $type['label'] }}</option>
                                        @endforeach
                                    </select>

                                    <!-- Dropdown Panel -->
                                    <div :id="`documentary-row-options-${row.uid}`" x-show="open" @click.away="open = false" role="listbox" class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto border border-slate-700 bg-slate-900 p-2 shadow-lg" x-cloak>
                                        <!-- Search Input -->
                                        <input type="text" x-model="search" placeholder="Buscar por código, serie o tipo..." class="mb-2 h-9 w-full border border-slate-700 bg-slate-950 px-2 text-sm text-white outline-none focus:border-amber-400">

                                        <!-- Options List -->
                                        <div class="space-y-1">
                                            <button type="button" role="option" @click="row.documentary_type_id = ''; open = false; search = '';" class="block w-full px-2 py-1.5 text-left text-sm text-slate-400 transition hover:bg-amber-500 hover:text-slate-950">
                                                Seleccionar tipo documental
                                            </button>
                                            <template x-for="opt in filteredOptions" :key="opt.id">
                                                <button type="button" role="option" @click="row.documentary_type_id = String(opt.id); open = false; search = '';" class="block w-full px-2 py-1.5 text-left text-sm text-slate-200 transition hover:bg-amber-500 hover:text-slate-950">
                                                    <span x-text="opt.label"></span>
                                                </button>
                                            </template>
                                            <div x-show="filteredOptions.length === 0" class="px-2 py-1.5 text-sm text-slate-500">
                                                No se encontraron resultados
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-200">Carpeta</label>
                                <input dusk="historical-row-folder" type="text" :name="`rows[${index}][folder]`" x-model="row.folder" class="block h-11 w-full border border-slate-700 bg-slate-800 px-3 text-sm text-white outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20">
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-200">Tomo / volumen</label>
                                <input dusk="historical-row-volume" type="text" :name="`rows[${index}][volume]`" x-model="row.volume" class="block h-11 w-full border border-slate-700 bg-slate-800 px-3 text-sm text-white outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20">
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-200">Año</label>
                                <input dusk="historical-row-year" type="number" min="1800" max="2200" :name="`rows[${index}][year]`" x-model="row.year" class="block h-11 w-full border border-slate-700 bg-slate-800 px-3 text-sm text-white outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20">
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-slate-200">Codigo / referencia</label>
                                <input dusk="historical-row-reference" type="text" :name="`rows[${index}][reference_code]`" x-model="row.reference_code" class="block h-11 w-full border border-slate-700 bg-slate-800 px-3 text-sm text-white outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20">
                            </div>

                            <div class="lg:col-span-2">
                                <label class="mb-1.5 block text-sm font-medium text-slate-200">Descripcion / asunto</label>
                                <input dusk="historical-row-description" type="text" :name="`rows[${index}][description]`" x-model="row.description" class="block h-11 w-full border border-slate-700 bg-slate-800 px-3 text-sm text-white outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20">
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
            <a href="{{ route('documents.index') }}" class="inline-flex h-11 items-center justify-center border border-slate-700 bg-slate-800 px-5 text-sm font-semibold text-slate-200">
                Volver
            </a>
            <button
                type="submit"
                dusk="historical-submit"
                :disabled="!selectedLocationId || hasMissingDocumentaryType()"
                class="inline-flex h-11 items-center justify-center bg-amber-500 px-5 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Incorporar al archivo central
            </button>
        </div>
    </form>
</div>
@endsection
