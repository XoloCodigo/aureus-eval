<?php

use Illuminate\Support\Facades\DB;
use Webkul\Inventory\Models\Lot;
use Webkul\Inventory\Models\Move;
use Webkul\Inventory\Models\MoveLine;
use Webkul\Manufacturing\Enums\ManufacturingOrderState;
use Webkul\Manufacturing\Models\Order;
use Webkul\Product\Models\Product;
use XoloCodigo\PetFood\Traceability\Models\LotGenealogy;

require_once __DIR__.'/../../../../../webkul/support/tests/Helpers/TestBootstrapHelper.php';

beforeEach(function () {
    TestBootstrapHelper::ensureERPInstalled();

    // Install plugin chain in dependency order so ALTER TABLE migrations
    // always run after the base table exists.
    if (! Schema::hasTable('inventories_lots')) {
        Artisan::call('inventories:install', ['--no-interaction' => true]);
    }

    if (! Schema::hasTable('manufacturing_orders')) {
        Artisan::call('manufacturing:install', ['--no-interaction' => true]);
    }

    if (! Schema::hasTable('petfood_lot_genealogies')) {
        Artisan::call('petfood:install', ['--no-interaction' => true]);
    }
});

/**
 * Builds a DRAFT manufacturing order with one raw-material move whose single
 * line consumes the given lot (or no lot, when $consumedLot is null).
 *
 * Order is persisted via saveQuietly() to skip the buggy Order::created ->
 * computeFinishedMoves() hook (upstream propagate_cancel issue). The move is
 * linked via a direct update because raw_material_order_id is not fillable.
 */
function makeRawMaterialOrder(Lot $producedLot, ?Lot $consumedLot, Product $consumedProduct, float $qty): Order
{
    $order = Order::factory()->make([
        'producing_lot_id' => $producedLot->id,
        'product_id'       => $producedLot->product_id,
        'state'            => ManufacturingOrderState::DRAFT,
    ]);
    $order->saveQuietly();

    // Move and line share the product's own UOM so the core MoveLine hook's
    // UOM conversion stays within one category (avoids the "different category"
    // exception thrown by UOM::convert during MoveLine creation).
    $move = Move::factory()->done()->create([
        'product_id' => $consumedProduct->id,
        'uom_id'     => $consumedProduct->uom_id,
    ]);

    DB::table('inventories_moves')
        ->where('id', $move->id)
        ->update(['raw_material_order_id' => $order->id]);

    MoveLine::factory()->done()->create([
        'move_id'    => $move->id,
        'product_id' => $consumedProduct->id,
        'uom_id'     => $consumedProduct->uom_id,
        'lot_id'     => $consumedLot?->id,
        'qty'        => $qty,
    ]);

    return $order->fresh();
}

it('captures lot genealogy when a manufacturing order is marked done', function () {
    $ptProduct = Product::factory()->create();
    $mpProduct = Product::factory()->create();

    $ptLot = Lot::factory()->create(['product_id' => $ptProduct->id]);
    $mpLot = Lot::factory()->create(['product_id' => $mpProduct->id]);

    $order = makeRawMaterialOrder($ptLot, $mpLot, $mpProduct, 12.5);

    $order->update(['state' => ManufacturingOrderState::DONE]);

    $row = LotGenealogy::where('manufacturing_order_id', $order->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->produced_lot_id)->toBe($ptLot->id)
        ->and($row->consumed_lot_id)->toBe($mpLot->id)
        ->and((float) $row->quantity)->toBe(12.5);
});

it('is idempotent: re-saving a done order does not duplicate rows', function () {
    $ptProduct = Product::factory()->create();
    $mpProduct = Product::factory()->create();

    $ptLot = Lot::factory()->create(['product_id' => $ptProduct->id]);
    $mpLot = Lot::factory()->create(['product_id' => $mpProduct->id]);

    $order = makeRawMaterialOrder($ptLot, $mpLot, $mpProduct, 5.0);

    $order->update(['state' => ManufacturingOrderState::DONE]);
    $order->fresh()->update(['priority' => '0']);

    expect(LotGenealogy::where('manufacturing_order_id', $order->id)->count())->toBe(1);
});

it('skips raw lines without a lot', function () {
    $ptProduct = Product::factory()->create();
    $mpProduct = Product::factory()->create();

    $ptLot = Lot::factory()->create(['product_id' => $ptProduct->id]);

    $order = makeRawMaterialOrder($ptLot, null, $mpProduct, 3.0);

    $order->update(['state' => ManufacturingOrderState::DONE]);

    expect(LotGenealogy::where('manufacturing_order_id', $order->id)->count())->toBe(0);
});

it('does nothing when the order is not yet done', function () {
    $ptProduct = Product::factory()->create();
    $mpProduct = Product::factory()->create();

    $ptLot = Lot::factory()->create(['product_id' => $ptProduct->id]);
    $mpLot = Lot::factory()->create(['product_id' => $mpProduct->id]);

    $order = makeRawMaterialOrder($ptLot, $mpLot, $mpProduct, 2.0);

    $order->update(['state' => ManufacturingOrderState::PROGRESS]);

    expect(LotGenealogy::where('manufacturing_order_id', $order->id)->count())->toBe(0);
});
