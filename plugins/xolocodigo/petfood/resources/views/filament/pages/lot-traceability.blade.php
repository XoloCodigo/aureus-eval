<x-filament-panels::page>
    <div class="max-w-md">
        <label for="lotId" class="block text-sm font-medium text-gray-950 dark:text-white">
            Lote
        </label>

        <select
            id="lotId"
            wire:model.live="lotId"
            class="mt-1 block w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5 text-sm"
        >
            <option value="">— Selecciona un lote —</option>
            @foreach ($this->lotOptions as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Recall: lotes de producto terminado afectados</x-slot>

            @forelse ($backward as $node)
                <div class="text-sm">
                    Lote #{{ $node['lot_id'] }} · producto #{{ $node['product_id'] }}
                    · cantidad {{ $node['quantity'] }} · nivel {{ $node['depth'] }}
                </div>
            @empty
                <div class="text-sm text-gray-500">Sin resultados.</div>
            @endforelse
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Origen: materias primas que formaron el lote</x-slot>

            @forelse ($forward as $node)
                <div class="text-sm">
                    Lote #{{ $node['lot_id'] }} · producto #{{ $node['product_id'] }}
                    · cantidad {{ $node['quantity'] }} · nivel {{ $node['depth'] }}
                </div>
            @empty
                <div class="text-sm text-gray-500">Sin resultados.</div>
            @endforelse
        </x-filament::section>
    </div>
</x-filament-panels::page>
