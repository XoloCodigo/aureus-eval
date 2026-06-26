<?php

use Webkul\Inventory\Enums\LocationType;
use Webkul\Inventory\Models\Location;
use Webkul\Inventory\Models\Lot;
use Webkul\Inventory\Models\ProductQuantity;
use Webkul\Inventory\Models\Warehouse;
use Webkul\Product\Models\Product;
use Webkul\Support\Models\Company;
use XoloCodigo\PetFood\Quality\Enums\MxQualityRole;
use XoloCodigo\PetFood\Quality\Services\QualityLocationService;

require_once __DIR__.'/../../../../../webkul/support/tests/Helpers/TestBootstrapHelper.php';

beforeEach(function () {
    TestBootstrapHelper::ensureERPInstalled();

    if (! Schema::hasTable('inventories_lots')) {
        Artisan::call('inventories:install', ['--no-interaction' => true]);
    }

    if (! Schema::hasColumn('inventories_locations', 'mx_quality_role')) {
        Artisan::call('migrate', [
            '--path'  => 'plugins/xolocodigo/petfood/database/migrations',
            '--force' => true,
        ]);
    }
});

/**
 * Creates a warehouse via the normal flow (its created-hook builds the internal
 * locations, so view_location_id etc. are populated). The hook also wires
 * reception rules that need a global supplier location, so we ensure one exists.
 */
function makeWarehouse(): Warehouse
{
    // The warehouse created-hook wires reception/delivery rules that reference
    // the global virtual locations (supplier/customer/inventory/production).
    foreach ([LocationType::SUPPLIER, LocationType::CUSTOMER, LocationType::INVENTORY, LocationType::PRODUCTION] as $type) {
        if (! Location::where('type', $type)->exists()) {
            Location::factory()->create(['type' => $type]);
        }
    }

    return Warehouse::factory()->create([
        'company_id' => Company::query()->value('id'),
    ]);
}

it('ensureFor creates the three quality locations idempotently', function () {
    $service = app(QualityLocationService::class);
    $warehouse = makeWarehouse();

    $created = $service->ensureFor($warehouse);

    expect($created)->toHaveCount(3)
        ->and($created->pluck('mx_quality_role')->sort()->values()->all())
        ->toBe(['quarantine', 'rejected', 'waste'])
        ->and($created->every(fn ($loc) => $loc->is_scrap === true))->toBeTrue();

    // Idempotent: a second call does not duplicate.
    $service->ensureFor($warehouse);

    expect(
        Location::where('warehouse_id', $warehouse->id)->whereNotNull('mx_quality_role')->count()
    )->toBe(3);
});

it('qualityLocations finds quality locations and filters by role', function () {
    $service = app(QualityLocationService::class);
    $warehouse = makeWarehouse();
    $service->ensureFor($warehouse);

    $mine = $service->qualityLocations()->where('warehouse_id', $warehouse->id);
    expect($mine)->toHaveCount(3);

    $quarantine = $service->qualityLocations(MxQualityRole::Quarantine)->where('warehouse_id', $warehouse->id);
    expect($quarantine)->toHaveCount(1)
        ->and($quarantine->first()->mx_quality_role)->toBe('quarantine');
});

it('material in a quality location is excluded from the lot available quantity', function () {
    $service = app(QualityLocationService::class);
    $warehouse = makeWarehouse();
    $quarantine = $service->ensureFor($warehouse)->firstWhere('mx_quality_role', 'quarantine');

    $product = Product::factory()->create();
    $lot = Lot::factory()->create(['product_id' => $product->id]);

    $normal = Location::factory()->create([
        'type'     => LocationType::INTERNAL,
        'is_scrap' => false,
    ]);

    // 10 usable in a normal internal location; 5 retained in quarantine.
    ProductQuantity::create([
        'product_id'              => $product->id,
        'location_id'             => $normal->id,
        'lot_id'                  => $lot->id,
        'quantity'                => 10,
        'reserved_quantity'       => 0,
        'inventory_diff_quantity' => 0,
    ]);
    ProductQuantity::create([
        'product_id'              => $product->id,
        'location_id'             => $quarantine->id,
        'lot_id'                  => $lot->id,
        'quantity'                => 5,
        'reserved_quantity'       => 0,
        'inventory_diff_quantity' => 0,
    ]);

    // getTotalQuantity counts only INTERNAL, non-scrap locations -> 10, not 15.
    expect((float) $lot->fresh()->total_quantity)->toBe(10.0);
});
