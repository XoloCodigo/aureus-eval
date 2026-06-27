<?php

use Webkul\Inventory\Models\Location;
use Webkul\Inventory\Models\Lot;
use Webkul\Product\Models\Product;
use XoloCodigo\PetFood\Quality\Enums\MxQualityRole;
use XoloCodigo\PetFood\Quality\Services\QualityLocationService;

require_once __DIR__.'/../../Support/PetFoodScenario.php';

beforeEach(fn () => PetFoodScenario::bootstrap());

it('ensureFor creates the three quality locations idempotently', function () {
    $service = app(QualityLocationService::class);
    $warehouse = PetFoodScenario::warehouse();

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
    $warehouse = PetFoodScenario::warehouse();
    $service->ensureFor($warehouse);

    $mine = $service->qualityLocations()->where('warehouse_id', $warehouse->id);
    expect($mine)->toHaveCount(3);

    $quarantine = $service->qualityLocations(MxQualityRole::Quarantine)->where('warehouse_id', $warehouse->id);
    expect($quarantine)->toHaveCount(1)
        ->and($quarantine->first()->mx_quality_role)->toBe('quarantine');
});

it('material in a quality location is excluded from the lot available quantity', function () {
    $service = app(QualityLocationService::class);
    $warehouse = PetFoodScenario::warehouse();
    $quarantine = $service->ensureFor($warehouse)->firstWhere('mx_quality_role', 'quarantine');

    $product = Product::factory()->create();
    $lot = Lot::factory()->create(['product_id' => $product->id]);

    // 10 usable in a normal internal location; 5 retained in quarantine.
    PetFoodScenario::stock($product, PetFoodScenario::internalLocation(), $lot, 10);
    PetFoodScenario::stock($product, $quarantine, $lot, 5);

    // getTotalQuantity counts only INTERNAL, non-scrap locations -> 10, not 15.
    expect((float) $lot->fresh()->total_quantity)->toBe(10.0);
});
