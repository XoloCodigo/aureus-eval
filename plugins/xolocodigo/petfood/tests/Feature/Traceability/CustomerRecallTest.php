<?php

use Webkul\Inventory\Enums\LocationType;
use Webkul\Inventory\Enums\MoveState;
use Webkul\Inventory\Models\Location;
use Webkul\Inventory\Models\Lot;
use Webkul\Inventory\Models\Move;
use Webkul\Inventory\Models\MoveLine;
use Webkul\Manufacturing\Enums\ManufacturingOrderState;
use Webkul\Partner\Models\Partner;
use Webkul\Product\Models\Product;
use XoloCodigo\PetFood\Traceability\Services\LotTraceabilityService;

require_once __DIR__.'/../../Support/PetFoodScenario.php';

beforeEach(fn () => PetFoodScenario::bootstrap());

/**
 * Build genealogy: $mpLot consumed to produce $ptLot, via a done MO.
 */
function recallChain(Lot $ptLot, Lot $mpLot, Product $mpProduct): void
{
    $order = PetFoodScenario::rawMaterialOrder($ptLot, $mpLot, $mpProduct, 10);
    $order->update(['state' => ManufacturingOrderState::DONE]);
}

it('lists the customer who received a finished lot containing the recalled raw lot', function () {
    $ptProduct = Product::factory()->create();
    $mpProduct = Product::factory()->create();
    $ptLot = Lot::factory()->create(['product_id' => $ptProduct->id]);
    $mpLot = Lot::factory()->create(['product_id' => $mpProduct->id]);

    recallChain($ptLot, $mpLot, $mpProduct);

    $customer = Partner::factory()->create();
    PetFoodScenario::customerDelivery($customer, $ptProduct, $ptLot, 4);

    $affected = app(LotTraceabilityService::class)->affectedCustomers($mpLot);

    expect($affected)->toHaveCount(1)
        ->and($affected->first()['customer_id'])->toBe($customer->id)
        ->and($affected->first()['lot_id'])->toBe($ptLot->id)
        ->and($affected->first()['quantity'])->toBe(4.0);
});

it('lists every customer who received the affected finished lot', function () {
    $ptProduct = Product::factory()->create();
    $mpProduct = Product::factory()->create();
    $ptLot = Lot::factory()->create(['product_id' => $ptProduct->id]);
    $mpLot = Lot::factory()->create(['product_id' => $mpProduct->id]);

    recallChain($ptLot, $mpLot, $mpProduct);

    $a = Partner::factory()->create();
    $b = Partner::factory()->create();
    PetFoodScenario::customerDelivery($a, $ptProduct, $ptLot, 3);
    PetFoodScenario::customerDelivery($b, $ptProduct, $ptLot, 5);

    $affected = app(LotTraceabilityService::class)->affectedCustomers($mpLot);

    expect($affected)->toHaveCount(2)
        ->and($affected->pluck('customer_id')->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

it('returns no customers when the finished lot was never delivered', function () {
    $ptProduct = Product::factory()->create();
    $mpProduct = Product::factory()->create();
    $ptLot = Lot::factory()->create(['product_id' => $ptProduct->id]);
    $mpLot = Lot::factory()->create(['product_id' => $mpProduct->id]);

    recallChain($ptLot, $mpLot, $mpProduct);

    expect(app(LotTraceabilityService::class)->affectedCustomers($mpLot))->toHaveCount(0);
});

it('ignores moves that are not deliveries to a customer location', function () {
    $ptProduct = Product::factory()->create();
    $mpProduct = Product::factory()->create();
    $ptLot = Lot::factory()->create(['product_id' => $ptProduct->id]);
    $mpLot = Lot::factory()->create(['product_id' => $mpProduct->id]);

    recallChain($ptLot, $mpLot, $mpProduct);

    // A done move carrying the PT lot but to an INTERNAL location (not a customer).
    $internal = Location::factory()->create(['type' => LocationType::INTERNAL]);
    $move = Move::factory()->create([
        'destination_location_id' => $internal->id,
        'partner_id'              => Partner::factory()->create()->id,
        'product_id'              => $ptProduct->id,
        'uom_id'                  => $ptProduct->uom_id,
        'state'                   => MoveState::DRAFT,
    ]);
    MoveLine::factory()->done()->create([
        'move_id'    => $move->id,
        'product_id' => $ptProduct->id,
        'uom_id'     => $ptProduct->uom_id,
        'lot_id'     => $ptLot->id,
        'qty'        => 7,
    ]);
    $move->update(['state' => MoveState::DONE]);

    expect(app(LotTraceabilityService::class)->affectedCustomers($mpLot))->toHaveCount(0);
});

it('catches a customer who received the raw lot directly (direct resale)', function () {
    $mpProduct = Product::factory()->create();
    $mpLot = Lot::factory()->create(['product_id' => $mpProduct->id]);

    $customer = Partner::factory()->create();
    PetFoodScenario::customerDelivery($customer, $mpProduct, $mpLot, 2);

    $affected = app(LotTraceabilityService::class)->affectedCustomers($mpLot);

    expect($affected)->toHaveCount(1)
        ->and($affected->first()['customer_id'])->toBe($customer->id)
        ->and($affected->first()['lot_id'])->toBe($mpLot->id);
});
