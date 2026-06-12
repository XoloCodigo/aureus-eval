<?php

namespace XoloCodigo\PetFood;

use Filament\Panel;
use Webkul\Manufacturing\Models\Order;
use Webkul\PluginManager\Console\Commands\InstallCommand;
use Webkul\PluginManager\Console\Commands\UninstallCommand;
use Webkul\PluginManager\Package;
use Webkul\PluginManager\PackageServiceProvider;
use XoloCodigo\PetFood\Traceability\Observers\ManufacturingOrderObserver;

class PetFoodServiceProvider extends PackageServiceProvider
{
    public static string $name = 'petfood';

    public static string $viewNamespace = 'petfood';

    public function configureCustomPackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasDependencies('products', 'inventories')
            ->hasViews()
            ->hasTranslations()
            ->hasMigrations([
                '2026_06_04_120000_add_petfood_industry_columns_to_products_products',
                '2026_06_11_120000_create_petfood_lot_genealogies_table',
            ])
            ->hasSeeder('XoloCodigo\\PetFood\\Database\\Seeders\\DatabaseSeeder')
            ->runsMigrations()
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->installDependencies()
                    ->runsMigrations()
                    ->runsSeeders();
            })
            ->hasUninstallCommand(function (UninstallCommand $command) {});
    }

    public function packageRegistered(): void
    {
        Panel::configureUsing(function (Panel $panel): void {
            $panel->plugin(PetFoodPlugin::make());
        });
    }

    public function packageBooted(): void
    {
        Order::observe(ManufacturingOrderObserver::class);
    }
}
