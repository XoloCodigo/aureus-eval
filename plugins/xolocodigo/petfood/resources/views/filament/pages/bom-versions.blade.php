<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Historial de versiones de receta. Cada cambio de fórmula sella la versión anterior y abre una nueva, conservando qué fórmula estuvo vigente y cuándo.
    </p>

    <x-filament::section>
        @if (empty($versions))
            <div class="text-sm text-gray-500">Aún no hay versiones de receta registradas.</div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-white/5">
                        <th class="py-2 pr-3">Receta</th>
                        <th class="py-2 pr-3">Versión</th>
                        <th class="py-2 pr-3">Desde</th>
                        <th class="py-2 pr-3">Hasta</th>
                        <th class="py-2 pr-3 text-right">Componentes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($versions as $row)
                        <tr class="border-b border-gray-100 dark:border-white/5 last:border-0">
                            <td class="py-2 pr-3">{{ $row['bom'] }}</td>
                            <td class="py-2 pr-3 tabular-nums">
                                v{{ $row['version'] }}
                                @if ($row['current'])
                                    <x-filament::badge color="success" class="ml-1 inline-flex">vigente</x-filament::badge>
                                @endif
                            </td>
                            <td class="py-2 pr-3 tabular-nums">{{ $row['from'] ?? '—' }}</td>
                            <td class="py-2 pr-3 tabular-nums">{{ $row['to'] ?? '—' }}</td>
                            <td class="py-2 pr-3 text-right tabular-nums">{{ $row['lines'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
