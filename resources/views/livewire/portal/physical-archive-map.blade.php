<div class="mx-auto max-w-6xl p-6 bg-[#0b1326] text-[#dae2fd] rounded-2xl border border-slate-800 shadow-2xl space-y-6" x-data="{ selectedLocation: @entangle('selectedLocationId'), zoom: 1.0 }">
    <!-- Header Section with Stitch Dark Aesthetics -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#171f33] to-[#0b1326] p-6 text-white border border-slate-800 shadow-xl">
        <div class="absolute -right-10 -top-10 h-56 w-56 rounded-full bg-[#4edea3]/5 blur-3xl"></div>
        
        <div class="relative z-10 flex flex-col justify-between gap-6 md:flex-row md:items-center">
            <div class="space-y-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#4edea3]/10 px-3 py-1 text-xs font-semibold text-[#4edea3] ring-1 ring-inset ring-[#4edea3]/20">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.008 1.24l.885 1.77a2.25 2.25 0 002.007 1.24h1.98a2.25 2.25 0 002.007-1.24l.885-1.77a2.25 2.25 0 012.007-1.24h3.86m-18 0h18a2.25 2.25 0 012.25 2.25v4.5A2.25 2.25 0 0118.07 21H5.93A2.25 2.25 0 013.6 18.75v-4.5A2.25 2.25 0 015.85 13.5zm0-9h12.3a2.25 2.25 0 012.23 1.96l.984 7.04H2.636l.984-7.04A2.25 2.25 0 015.85 4.5z" />
                    </svg>
                    Mapa Visual de Custodia
                </span>
                <h1 class="text-3xl font-extrabold tracking-tight bg-clip-text text-white">
                    Custodia Documental
                </h1>
                <p class="max-w-2xl text-sm leading-relaxed text-slate-450">
                    Localiza de manera precisa dónde se encuentra cada folio y expediente digitalizado dentro de la bodega principal.
                </p>
            </div>
            
            <!-- Compact Shelf Navigation Controls -->
            <div class="flex items-center gap-3 bg-[#171f33] p-2.5 rounded-xl border border-slate-800 shadow-inner">
                <button 
                    wire:click="previousShelf" 
                    class="p-2 rounded bg-[#0b1326] hover:bg-[#2d3449] border border-slate-700 text-[#4edea3] transition disabled:opacity-30 disabled:hover:bg-[#0b1326] focus:outline-none"
                    {{ $selectedShelf === '01' ? 'disabled' : '' }}
                    title="Estante Anterior"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                </button>
                
                <div class="flex flex-col items-center px-4">
                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest leading-none">Estante / Rack</span>
                    <select 
                        wire:model.live="selectedShelf" 
                        class="bg-[#0b1326] border border-slate-750 text-[#4edea3] font-black text-sm rounded-lg py-1 px-3 focus:ring-1 focus:ring-[#4edea3] cursor-pointer text-center outline-none mt-1"
                        wire:key="shelf-selector"
                    >
                        @foreach ($shelvesList as $shelf)
                            @php
                                $count = $shelfCounts->get($shelf) ?? 0;
                                $label = $count > 0 ? "Rack {$shelf} ({$count} docs)" : "Rack {$shelf} (Vacío)";
                            @endphp
                            <option value="{{ $shelf }}" class="bg-[#171f33] text-white">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                
                <button 
                    wire:click="nextShelf" 
                    class="p-2 rounded bg-[#0b1326] hover:bg-[#2d3449] border border-slate-700 text-[#4edea3] transition disabled:opacity-30 disabled:hover:bg-[#0b1326] focus:outline-none"
                    {{ $selectedShelf === '40' ? 'disabled' : '' }}
                    title="Siguiente Estante"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Legend and Visual Control Header -->
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 pb-4">
        <div class="flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-[#4edea3] animate-pulse"></span>
            <h2 class="text-xs font-mono tracking-widest text-slate-400 uppercase">
                Estante {{ $selectedShelf }} - Bahía A{{ $selectedShelf }}
            </h2>
        </div>
        
        <div class="flex flex-wrap items-center gap-3 rounded-lg bg-[#171f33] p-2 border border-slate-800 text-xs">
            <span class="font-bold text-slate-400 uppercase tracking-wider text-[9px] mr-1">Leyenda:</span>
            <span class="flex items-center gap-1.5 font-medium text-slate-400">
                <span class="h-2.5 w-2.5 rounded bg-[#3c4a42]/30 border border-[#3c4a42]/60"></span>
                Vacío (0%)
            </span>
            <span class="flex items-center gap-1.5 font-medium text-[#4edea3]">
                <span class="h-2.5 w-2.5 rounded bg-[#4edea3]"></span>
                Bajo (&lt;50%)
            </span>
            <span class="flex items-center gap-1.5 font-medium text-[#ffb95f]">
                <span class="h-2.5 w-2.5 rounded bg-[#ffb95f]"></span>
                Medio (50-90%)
            </span>
            <span class="flex items-center gap-1.5 font-medium text-[#ffb4ab]">
                <span class="h-2.5 w-2.5 rounded bg-[#ffb4ab]"></span>
                Crítico (&gt;90%)
            </span>
        </div>
    </div>

    <!-- The Metallic Rack (Visual Elevation) -->
    <div class="relative overflow-x-auto rounded-2xl border border-slate-800 bg-[#171f33] p-6 shadow-2xl">
        <!-- Rack Grid containing shelves (min-width forces a standard size without responsive flattening) -->
        <div class="flex flex-col gap-6 min-w-[960px] mx-auto">
            <!-- Top Structural Beam -->
            <div class="h-3 w-full rounded bg-gradient-to-r from-slate-700 via-slate-600 to-slate-800 shadow-md border-b border-slate-900"></div>

            <!-- Loop levels from top to bottom (6 to 1) -->
            @foreach (array_reverse($entrepanos) as $entrepano)
                <div class="flex items-center gap-4">
                    <!-- Row identifier styling -->
                    <div class="w-24 shrink-0 text-right flex flex-col justify-center">
                         <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest leading-none">Nivel</span>
                         <span class="text-2xl font-black text-[#4edea3] leading-tight">{{ $entrepano }}</span>
                    </div>

                    <!-- Box Shelf Container (Fixed size columns, 8 columns) -->
                    <div class="flex-1 bg-[#131b2e] border border-slate-800/60 p-3 rounded-xl flex gap-3 h-28 shadow-inner justify-between items-center">
                        @foreach ($cajas as $caja)
                            @php
                                $loc = $grid[$entrepano][$caja] ?? null;
                                $docCount = $loc ? $loc->documents_count : 0;
                                
                                $capacityTotal = $loc ? ($loc->capacity_total ?: 25) : 25;
                                $percentage = $capacityTotal > 0 ? min(100, ($docCount / $capacityTotal) * 100) : 0;
                                
                                if ($docCount === 0) {
                                    $boxBg = 'bg-[#2d3449]/80 border-[#3c4a42]/50 text-slate-400 hover:border-[#4edea3] hover:shadow-[0_0_10px_rgba(78,222,163,0.25)]';
                                    $barBg = 'bg-[#3c4a42]';
                                    $barWidth = '0%';
                                    $badgeColor = 'text-slate-455';
                                } elseif ($percentage < 50) {
                                    $boxBg = 'bg-[#171f33] border-[#4edea3]/30 text-[#4edea3] hover:border-[#4edea3] hover:shadow-[0_0_12px_rgba(78,222,163,0.4),inset_0_0_4px_rgba(78,222,163,0.2)]';
                                    $barBg = 'bg-[#4edea3]';
                                    $barWidth = $percentage . '%';
                                    $badgeColor = 'text-[#4edea3]';
                                } elseif ($percentage < 90) {
                                    $boxBg = 'bg-[#171f33] border-[#ffb95f]/30 text-[#ffb95f] hover:border-[#ffb95f] hover:shadow-[0_0_12px_rgba(255,185,95,0.4),inset_0_0_4px_rgba(255,185,95,0.2)]';
                                    $barBg = 'bg-[#ffb95f]';
                                    $barWidth = $percentage . '%';
                                    $badgeColor = 'text-[#ffb95f]';
                                } else {
                                    $boxBg = 'bg-[#171f33] border-[#ffb4ab]/30 text-[#ffb4ab] hover:border-[#ffb4ab] hover:shadow-[0_0_12px_rgba(255,180,171,0.4),inset_0_0_4px_rgba(255,180,171,0.2)]';
                                    $barBg = 'bg-[#ffb4ab] animate-pulse';
                                    $barWidth = '100%';
                                    $badgeColor = 'text-[#ffb4ab]';
                                }
                            @endphp

                            @if ($loc)
                                <!-- Box component with fixed size (w-[96px] h-22) -->
                                <div 
                                    x-on:click="selectedLocation = {{ $loc->id }}"
                                    wire:click="selectLocation({{ $loc->id }})"
                                    class="w-[96px] h-22 rounded-lg border p-2 flex flex-col justify-between cursor-pointer transition-all duration-200 group relative {{ $selectedLocationId === $loc->id ? 'ring-2 ring-[#4edea3] border-[#4edea3] shadow-[0_0_15px_rgba(78,222,163,0.4)]' : '' }} {{ $boxBg }}"
                                >
                                    <!-- Box ID & Count -->
                                    <div class="flex justify-between items-start leading-none">
                                        <span class="text-[8px] font-mono text-slate-500 group-hover:text-white transition-colors">
                                            C{{ $caja }}
                                        </span>
                                        <span class="text-[9px] font-mono font-bold {{ $badgeColor }}">
                                            {{ $docCount }}
                                        </span>
                                    </div>

                                    <!-- Handle slot -->
                                    <div class="h-2 w-6 rounded-full bg-[#060e20] border border-slate-800 mx-auto shadow-inner flex items-center justify-center">
                                         <span class="block w-2.5 h-0.5 bg-slate-800 rounded-full"></span>
                                    </div>

                                    <!-- Capacity Fill Bar -->
                                    <div class="w-full bg-[#060e20] h-1.5 rounded-full overflow-hidden mt-1.5">
                                         <div class="h-full rounded-full transition-all duration-300 {{ $barBg }}" style="width: {{ $barWidth }}"></div>
                                    </div>
                                </div>
                            @else
                                <!-- Empty slot (Fixed size w-[96px] h-22) -->
                                <div class="w-[96px] h-22 border border-dashed border-[#3c4a42]/30 rounded-lg flex items-center justify-center bg-[#0b1326]/20">
                                    <span class="text-[8px] font-mono text-slate-600 font-bold uppercase tracking-wider">VACÍO</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                <!-- Steel Support Beam -->
                <div class="h-2.5 w-full rounded bg-gradient-to-r from-slate-700 via-slate-600 to-slate-800 shadow-md"></div>
            @endforeach
        </div>
    </div>

    <!-- Integrated Detail Section (Smoothly appears below the shelves) -->
    <div 
        class="rounded-2xl border border-slate-800 bg-[#171f33] p-6 shadow-2xl space-y-6"
        x-show="!!selectedLocation" 
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-4"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-y-0"
        x-transition:leave-end="opacity-0 transform translate-y-4"
        x-cloak
    >
        <!-- Section Header -->
        <div class="flex justify-between items-start border-b border-slate-800 pb-4">
            <div class="space-y-1">
                <span class="inline-flex items-center gap-1 rounded bg-[#4edea3]/10 px-2 py-0.5 text-[9px] font-extrabold text-[#4edea3] uppercase tracking-wider border border-[#4edea3]/20">
                    Panel de Detalle de Caja
                </span>
                <h2 class="text-2xl font-black tracking-tight text-white">
                    Caja {{ $selectedLocation?->structured_data['caja'] ?? '' }}
                </h2>
                <div class="flex items-center gap-2 text-xs text-slate-400 font-medium">
                    <span>Estante {{ $selectedLocation?->structured_data['estante'] ?? '' }}</span>
                    <span class="text-slate-650">•</span>
                    <span>Entrepaño {{ $selectedLocation?->structured_data['entrepaño'] ?? '' }}</span>
                </div>
            </div>
            
            <button 
                x-on:click="selectedLocation = null; $wire.selectLocation(null)"
                class="rounded-lg p-1.5 text-slate-400 hover:bg-white/5 hover:text-white transition focus:outline-none"
            >
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Wide Grid Layout for Desktop (3 columns: Left metadata & search, Right documents list) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Left Column: Location Metadata & Search -->
            <div class="space-y-6 md:col-span-1">
                @if ($selectedLocation)
                    <div class="grid grid-cols-1 gap-4">
                        <div class="rounded-xl bg-[#0b1326] p-4 border border-slate-850 shadow-inner">
                            <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-500 mb-1">Identificador QR</span>
                            <span class="text-xs font-mono font-bold text-[#4edea3] break-all">
                                {{ $selectedLocation->code }}
                            </span>
                        </div>
                        <div class="rounded-xl bg-[#0b1326] p-4 border border-slate-850 shadow-inner">
                            <span class="block text-[9px] font-bold uppercase tracking-wider text-slate-500 mb-1">Total Expedientes</span>
                            <span class="text-sm font-black text-white">
                                {{ $selectedLocation->documents_count }} registros
                            </span>
                        </div>
                    </div>
                @endif

                <!-- Search box inside the active box context -->
                <div class="space-y-3 bg-[#0b1326]/50 p-4 rounded-xl border border-slate-800">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-450 font-mono">
                            Buscar en esta Caja
                        </h3>
                        <div wire:loading wire:target="searchQuery">
                            <span class="flex h-4 w-4 animate-spin rounded-full border-2 border-[#4edea3] border-t-transparent"></span>
                        </div>
                    </div>

                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input 
                            type="text" 
                            wire:model.live.debounce.300ms="searchQuery" 
                            placeholder="Buscar por título, número..." 
                            class="w-full rounded-xl border border-slate-850 bg-[#0b1326] py-3 pl-11 pr-4 text-sm text-white placeholder-slate-650 focus:border-[#4edea3] focus:outline-none focus:ring-1 focus:ring-[#4edea3] transition"
                        />
                    </div>
                </div>
            </div>

            <!-- Right Column: Document Grid (2 columns on desktop) -->
            <div class="md:col-span-2 space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-450 font-mono">
                    Expedientes Almacenados
                </h3>

                @if ($documents && $documents->count() > 0)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($documents as $doc)
                            <div class="relative flex flex-col justify-between rounded-xl border border-slate-800 bg-[#0b1326]/60 p-4 transition-all duration-200 hover:border-slate-700 hover:bg-[#0b1326]">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="space-y-1 min-w-0">
                                        <a 
                                            href="{{ route('documents.show', $doc->id) }}" 
                                            class="truncate block text-sm font-bold text-slate-200 hover:text-[#4edea3] transition-colors"
                                        >
                                            {{ $doc->title }}
                                        </a>
                                        <span class="block font-mono text-xs text-slate-500">
                                            N° {{ $doc->document_number }}
                                        </span>
                                    </div>
                                    <span class="inline-flex rounded bg-[#4edea3]/10 px-2 py-0.5 text-[9px] font-bold text-[#4edea3] uppercase tracking-wide shrink-0">
                                        {{ $doc->digital_document_type ?: 'Físico' }}
                                    </span>
                                </div>

                                <div class="mt-4 flex items-center justify-between border-t border-slate-800/80 pt-3 text-xs text-slate-450">
                                    <span class="flex items-center gap-1.5 font-mono">
                                        <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h12.75A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h12.75A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
                                        </svg>
                                        {{ $doc->created_at?->format('d/m/Y') }}
                                    </span>
                                    
                                    <a 
                                        href="{{ route('documents.show', $doc->id) }}" 
                                        class="inline-flex items-center gap-1 text-xs font-bold text-[#4edea3] hover:text-[#4edea3]/80 transition-colors"
                                    >
                                        Ver Detalles
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pagination Footer -->
                    <div class="pt-4 border-t border-slate-800">
                        {{ $documents->links() }}
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-16 text-slate-500 bg-[#0b1326]/30 rounded-xl border border-slate-800/60">
                        <svg class="h-16 w-16 opacity-20 mb-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                        <h4 class="text-sm font-bold text-slate-400">Sin Coincidencias</h4>
                        <p class="mt-1 text-center text-xs max-w-xs text-slate-500">
                            {{ !empty($searchQuery) ? 'No se encontraron documentos en esta caja.' : 'Esta caja no contiene expedientes físicos registrados.' }}
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
