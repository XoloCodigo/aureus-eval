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
        // Internal traceability/quality pages belong to the back office only,
        // never the customer panel.
        $panel->when($panel->getId() === 'admin', function (Panel $panel): void {
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
                )
                ->discoverPages(
                    in: __DIR__.'/Recipes/Filament/Pages',
                    for: 'XoloCodigo\\PetFood\\Recipes\\Filament\\Pages'
                );
        });
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
