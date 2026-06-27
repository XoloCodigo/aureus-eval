<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Material retenido por control de calidad. El stock en estas áreas no está disponible para producción ni venta hasta liberarlo.
    </p>

    <div class="grid gap-6 md:grid-cols-3">
        @foreach ($groups as $group)
            <x-filament::section>
                <x-slot name="heading">{{ $group['label'] }}</x-slot>

                @forelse ($group['locations'] as $location)
                    <div class="flex items-center justify-between gap-3 py-1 text-sm border-b border-gray-100 dark:border-white/5 last:border-0">
                        <span class="truncate">{{ $location['name'] }}</span>
                        <span class="font-medium tabular-nums {{ $location['retained'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400' }}">
                            {{ rtrim(rtrim(number_format($location['retained'], 4, '.', ','), '0'), '.') ?: '0' }}
                        </span>
                    </div>
                @empty
                    <div class="text-sm text-gray-500">Sin áreas definidas.</div>
                @endforelse
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
