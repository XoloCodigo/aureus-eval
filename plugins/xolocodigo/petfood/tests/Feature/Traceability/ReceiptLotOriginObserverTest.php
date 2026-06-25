<?php

use Webkul\Inventory\Enums\LocationType;
use Webkul\Inventory\Enums\MoveState;
use Webkul\Inventory\Models\Location;
use Webkul\Inventory\Models\Lot;
use Webkul\Inventory\Models\Move;
use Webkul\Inventory\Models\MoveLine;
use Webkul\Partner\Models\Partner;
use Webkul\Product\Models\Product;

require_once __DIR__.'/../../../../../webkul/support/tests/Helpers/TestBootstrapHelper.php';

beforeEach(function () {
    TestBootstrapHelper::ensureERPInstalled();

    if (! Schema::hasTable('inventories_lots')) {
        Artisan::call('inventories:install', ['--no-interaction' => true]);
    }

    if (! Schema::hasColumn('inventories_lots', 'mx_supplier_id')) {
        Artisan::call('migrate', [
            '--path'  => 'plugins/xolocodigo/petfood/database/migrations',
            '--force' => true,
        ]);
    }
});

/**
 * Builds a DRAFT move whose source is a SUPPLIER location (a receipt) carrying
 * the given lot, attributed to the given supplier partner. Marking the move
 * DONE in the test triggers the observer.
 */
function makeSupplierReceipt(?Partner $supplier, Product $product, Lot $lot): Move
{
    $supplierLocation = Location::factory()->create(['type' => LocationType::SUPPLIER]);

    $move = Move::factory()->create([
        'source_location_id' => $supplierLocation->id,
        'partner_id'         => $supplier?->id,
        'product_id'         => $product->id,
        'uom_id'             => $product->uom_id,
        'state'              => MoveState::DRAFT,
    ]);

    MoveLine::factory()->done()->create([
        'move_id'    => $move->id,
        'product_id' => $product->id,
        'uom_id'     => $product->uom_id,
        'lot_id'     => $lot->id,
    ]);

    return $move->fresh();
}

it('stamps the supplier on a lot when goods are received from a supplier', function () {
    $supplier = Partner::factory()->create();
    $product = Product::factory()->create();
    $lot = Lot::factory()->create(['product_id' => $product->id]);

    $move = makeSupplierReceipt($supplier, $product, $lot);
    $move->update(['state' => MoveState::DONE]);

    expect((int) $lot->fresh()->mx_supplier_id)->toBe($supplier->id);
});

it('does not stamp a supplier for a non-supplier (internal) move', function () {
    $supplier = Partner::factory()->create();
    $product = Product::factory()->create();
    $lot = Lot::factory()->create(['product_id' => $product->id]);

    $internalLocation = Location::factory()->create(['type' => LocationType::INTERNAL]);

    $move = Move::factory()->create([
        'source_location_id' => $internalLocation->id,
        'partner_id'         => $supplier->id,
        'product_id'         => $product->id,
        'uom_id'             => $product->uom_id,
        'state'              => MoveState::DRAFT,
    ]);

    MoveLine::factory()->done()->create([
        'move_id'    => $move->id,
        'product_id' => $product->id,
        'uom_id'     => $product->uom_id,
        'lot_id'     => $lot->id,
    ]);

    $move->update(['state' => MoveState::DONE]);

    expect($lot->fresh()->mx_supplier_id)->toBeNull();
});

it('does not overwrite an existing supplier on the lot', function () {
    $original = Partner::factory()->create();
    $other = Partner::factory()->create();
    $product = Product::factory()->create();
    $lot = Lot::factory()->create(['product_id' => $product->id]);

    Lot::query()->whereKey($lot->id)->update(['mx_supplier_id' => $original->id]);

    $move = makeSupplierReceipt($other, $product, $lot);
    $move->update(['state' => MoveState::DONE]);

    expect((int) $lot->fresh()->mx_supplier_id)->toBe($original->id);
});
