<x-filament-panels::page>
    <x-filament::section icon="heroicon-o-magnifying-glass">
        <x-slot name="heading">¿Qué lote quieres rastrear?</x-slot>
        <x-slot name="description">
            Un lote de materia prima muestra en qué productos terminó y a qué clientes llegó (recall).
            Un lote de producto terminado muestra de qué está hecho, hasta el proveedor.
        </x-slot>

        {{ $this->form }}
    </x-filament::section>

    @if ($card = $this->lotCard)
        <x-filament::section icon="heroicon-o-tag">
            <x-slot name="heading">Lote {{ $this->lotName }}</x-slot>
            @if ($card['Retenido en calidad'])
                <x-slot name="afterHeader">
                    <x-filament::badge color="warning" icon="heroicon-m-shield-exclamation">Retenido por calidad</x-filament::badge>
                </x-slot>
            @endif

            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (array_filter($card) as $field => $value)
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $field }}</dt>
                        <dd class="text-sm font-medium text-gray-950 dark:text-white">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>

        <x-filament::section icon="heroicon-o-information-circle" icon-color="primary" compact>
            <x-slot name="heading">{{ $this->recallSummary }}</x-slot>
        </x-filament::section>

        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ([
                ['title' => '¿En qué se usó?', 'description' => 'Hacia el cliente: lotes producidos con este lote.', 'icon' => 'heroicon-o-arrow-down-right', 'nodes' => $usedIn, 'verb' => 'se usaron', 'empty' => 'Todavía no se usa en ninguna producción.'],
                ['title' => '¿De qué está hecho?', 'description' => 'Hacia el proveedor: lotes que se consumieron para producirlo.', 'icon' => 'heroicon-o-arrow-up-left', 'nodes' => $madeOf, 'verb' => 'aportó', 'empty' => 'No se produjo en planta: llegó de un proveedor.'],
            ] as $panel)
                <x-filament::section :icon="$panel['icon']">
                    <x-slot name="heading">{{ $panel['title'] }}</x-slot>
                    <x-slot name="description">{{ $panel['description'] }}</x-slot>

                    @forelse ($panel['nodes'] as $node)
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 border-b border-gray-100 py-2 text-sm last:border-0 dark:border-white/5"
                             style="padding-left: {{ ($node['depth'] - 1) * 1.5 }}rem">
                            @if ($node['depth'] > 1)
                                <span class="text-gray-400">↳</span>
                            @endif
                            <span class="font-semibold text-gray-950 dark:text-white">{{ $node['lot_name'] }}</span>
                            <span>{{ $node['product_name'] }}</span>
                            @if ($type = $this->itemTypeLabel($node['type']))
                                <x-filament::badge size="sm" color="gray">{{ $type }}</x-filament::badge>
                            @endif
                            <span class="text-gray-500 dark:text-gray-400">
                                {{ $panel['verb'] }} {{ $this->qty($node['quantity'], $node['uom']) }}@if ($node['order']) · {{ $node['order'] }}@endif
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $panel['empty'] }}</p>
                    @endforelse
                </x-filament::section>
            @endforeach
        </div>

        <x-filament::section icon="heroicon-o-users">
            <x-slot name="heading">Clientes afectados</x-slot>
            <x-slot name="description">Entregas de producto que contiene este lote. Son a quienes hay que avisar.</x-slot>

            @if (empty($affectedCustomers))
                <p class="text-sm text-gray-500 dark:text-gray-400">Ningún cliente ha recibido producto con este lote.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500 dark:border-white/5 dark:text-gray-400">
                            <th class="py-2 pr-3">Cliente</th>
                            <th class="py-2 pr-3">Lote entregado</th>
                            <th class="py-2 pr-3">Producto</th>
                            <th class="py-2 pr-3 text-right">Cantidad</th>
                            <th class="py-2 pr-3">Fecha</th>
                            <th class="py-2 pr-3">Entrega</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($affectedCustomers as $row)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-white/5">
                                <td class="py-2 pr-3 font-medium text-gray-950 dark:text-white">{{ $row['customer'] ?? 'Sin cliente' }}</td>
                                <td class="py-2 pr-3">{{ $row['lot'] }}</td>
                                <td class="py-2 pr-3">{{ $row['product'] }}</td>
                                <td class="py-2 pr-3 text-right tabular-nums">{{ $this->qty($row['quantity'], $row['uom']) }}</td>
                                <td class="py-2 pr-3 tabular-nums">{{ $row['shipped_at'] ? \Illuminate\Support\Carbon::parse($row['shipped_at'])->format('d/m/Y') : '—' }}</td>
                                <td class="py-2 pr-3">{{ $row['reference'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
