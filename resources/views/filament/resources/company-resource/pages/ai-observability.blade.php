<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Runs hoy</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $this->overview['runs_today'] ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Éxitos (mes)</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-700 dark:text-emerald-400">{{ $this->overview['runs_success_month'] ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Fallos (mes)</p>
            <p class="mt-2 text-2xl font-semibold text-rose-700 dark:text-rose-400">{{ $this->overview['runs_failed_month'] ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Costo (mes)</p>
            <p class="mt-2 text-2xl font-semibold text-indigo-700 dark:text-indigo-400">
                ${{ number_format((($this->overview['cost_month_cents'] ?? 0) / 100), 2) }}
            </p>
        </div>
    </div>

    <div class="mt-6 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Por proveedor (mes actual)</h3>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Proveedor</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Runs</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Éxitos</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Fallos</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Costo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($this->providerRows as $row)
                        <tr>
                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $row['provider'] }}</td>
                            <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">{{ $row['runs_total'] }}</td>
                            <td class="px-3 py-2 text-sm text-emerald-700 dark:text-emerald-400">{{ $row['runs_success'] }}</td>
                            <td class="px-3 py-2 text-sm text-rose-700 dark:text-rose-400">{{ $row['runs_failed'] }}</td>
                            <td class="px-3 py-2 text-sm text-indigo-700 dark:text-indigo-400">{{ $row['cost_human'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">Sin datos para el mes actual.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Últimos 7 días</h3>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Día</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Runs</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Éxitos</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Fallos</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Costo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($this->dailyRows as $row)
                        <tr>
                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">{{ $row['day'] }}</td>
                            <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">{{ $row['runs_total'] }}</td>
                            <td class="px-3 py-2 text-sm text-emerald-700 dark:text-emerald-400">{{ $row['runs_success'] }}</td>
                            <td class="px-3 py-2 text-sm text-rose-700 dark:text-rose-400">{{ $row['runs_failed'] }}</td>
                            <td class="px-3 py-2 text-sm text-indigo-700 dark:text-indigo-400">{{ $row['cost_human'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
