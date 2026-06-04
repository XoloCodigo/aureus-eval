<?php

namespace XoloCodigo\PetFood;

use Filament\Panel;
use Webkul\PluginManager\Package;
use Webkul\PluginManager\PackageServiceProvider;

class PetFoodServiceProvider extends PackageServiceProvider
{
    public static string $name = 'petfood';

    public static string $viewNamespace = 'petfood';

    public function configureCustomPackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasViews()
            ->hasTranslations()
            ->hasMigrations([
                // Add migrations here as they are created, e.g.:
                // '2026_06_03_000000_add_mx_fiscal_ids_to_employees_employees',
            ])
            ->hasSeeder('XoloCodigo\\PetFood\\Database\\Seeders\\DatabaseSeeder')
            ->runsMigrations();
    }

    public function packageRegistered(): void
    {
        Panel::configureUsing(function (Panel $panel): void {
            $panel->plugin(PetFoodPlugin::make());
        });
    }

    public function packageBooted(): void
    {
        //
    }
}
