<?php

namespace XoloCodigo\PetFood;

use Filament\Contracts\Plugin;
use Filament\Panel;

class PetFoodPlugin implements Plugin
{
    public function getId(): string
    {
        return 'petfood';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function register(Panel $panel): void
    {
        $panel
            ->discoverResources(
                in: __DIR__.'/Filament/Resources',
                for: 'XoloCodigo\\PetFood\\Filament\\Resources'
            )
            ->discoverPages(
                in: __DIR__.'/Filament/Pages',
                for: 'XoloCodigo\\PetFood\\Filament\\Pages'
            )
            ->discoverPages(
                in: __DIR__.'/Traceability/Filament/Pages',
                for: 'XoloCodigo\\PetFood\\Traceability\\Filament\\Pages'
            )
            ->discoverPages(
                in: __DIR__.'/Quality/Filament/Pages',
                for: 'XoloCodigo\\PetFood\\Quality\\Filament\\Pages'
            );
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
