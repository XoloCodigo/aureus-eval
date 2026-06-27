<?php

use Webkul\Inventory\Models\Lot;
use Webkul\Manufacturing\Enums\ManufacturingOrderState;
use Webkul\Product\Models\Product;
use XoloCodigo\PetFood\Traceability\Models\LotGenealogy;

require_once __DIR__.'/../../Support/PetFoodScenario.php';

beforeEach(fn () => PetFoodScenario::bootstrap());

it('captures lot genealogy when a manufacturing order is marked done', function () {
    $ptProduct = Product::factory()->create();
    $mpProduct = Product::factory()->create();

    $ptLot = Lot::factory()->create(['product_id' => $ptProduct->id]);
    $mpLot = Lot::factory()->create(['product_id' => $mpProduct->id]);

    $order = PetFoodScenario::rawMaterialOrder($ptLot, $mpLot, $mpProduct, 12.5);
    $order->update(['state' => ManufacturingOrderState::DONE]);

    $row = LotGenealogy::where('manufacturing_order_id', $order->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->produced_lot_id)->toBe($ptLot->id)
        ->and($row->consumed_lot_id)->toBe($mpLot->id)
        ->and((float) $row->quantity)->toBe(12.5);
});

it('is idempotent: re-saving a done order does not duplicate rows', function () {
    $ptLot = Lot::factory()->create();
    $mpProduct = Product::factory()->create();
    $mpLot = Lot::factory()->create(['product_id' => $mpProduct->id]);

    $order = PetFoodScenario::rawMaterialOrder($ptLot, $mpLot, $mpProduct, 5.0);
    $order->update(['state' => ManufacturingOrderState::DONE]);
    $order->fresh()->update(['priority' => '0']);

    expect(LotGenealogy::where('manufacturing_order_id', $order->id)->count())->toBe(1);
});

it('skips raw lines without a lot', function () {
    $ptLot = Lot::factory()->create();
    $mpProduct = Product::factory()->create();

    $order = PetFoodScenario::rawMaterialOrder($ptLot, null, $mpProduct, 3.0);
    $order->update(['state' => ManufacturingOrderState::DONE]);

    expect(LotGenealogy::where('manufacturing_order_id', $order->id)->count())->toBe(0);
});

it('does nothing when the order is not yet done', function () {
    $ptLot = Lot::factory()->create();
    $mpProduct = Product::factory()->create();
    $mpLot = Lot::factory()->create(['product_id' => $mpProduct->id]);

    $order = PetFoodScenario::rawMaterialOrder($ptLot, $mpLot, $mpProduct, 2.0);
    $order->update(['state' => ManufacturingOrderState::PROGRESS]);

    expect(LotGenealogy::where('manufacturing_order_id', $order->id)->count())->toBe(0);
});
