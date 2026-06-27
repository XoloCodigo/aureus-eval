<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Inventory\Enums\LocationType;
use Webkul\Inventory\Enums\MoveState;
use Webkul\Inventory\Models\Location;
use Webkul\Inventory\Models\Lot;
use Webkul\Inventory\Models\Move;
use Webkul\Inventory\Models\MoveLine;
use Webkul\Inventory\Models\ProductQuantity;
use Webkul\Inventory\Models\Warehouse;
use Webkul\Manufacturing\Enums\ManufacturingOrderState;
use Webkul\Manufacturing\Models\BillOfMaterial;
use Webkul\Manufacturing\Models\BillOfMaterialLine;
use Webkul\Manufacturing\Models\Order;
use Webkul\Partner\Models\Partner;
use Webkul\Product\Models\Product;
use Webkul\Support\Models\Company;
use XoloCodigo\PetFood\Recipes\Services\BomVersionService;

require_once __DIR__.'/../../../../webkul/support/tests/Helpers/TestBootstrapHelper.php';

/**
 * Reusable test-scenario builder for the petfood plugin.
 *
 * Creating core entities (warehouse, manufacturing order, receipt move, stock)
 * in tests trips repeated hooks/bugs/quirks of the fork. This class encapsulates
 * the confirmed-working way to create each one, so tests don't re-discover the
 * traps. The lessons behind each method are in memory (project-s2/s2.1/s5).
 *
 * No namespace + require_once (matching TestBootstrapHelper) so any Pest test
 * file can `require_once` it and call PetFoodScenario::method().
 */
class PetFoodScenario
{
    /**
     * One-time bootstrap for a petfood feature test: ERP + inventories + the
     * petfood migrations. Call from beforeEach().
     *
     * Note (Windows): do NOT run `php artisan optimize:clear` — it deletes
     * bootstrap/cache/packages.php and the next artisan call fails because PHP's
     * is_writable() reads the read-only attribute Windows sets on directories.
     */
    public static function bootstrap(): void
    {
        TestBootstrapHelper::ensureERPInstalled();

        if (! Schema::hasTable('inventories_lots')) {
            Artisan::call('inventories:install', ['--no-interaction' => true]);
        }

        if (! Schema::hasTable('manufacturing_orders')) {
            Artisan::call('manufacturing:install', ['--no-interaction' => true]);
        }

        if (! Schema::hasColumn('inventories_lots', 'mx_supplier_id')
            || ! Schema::hasColumn('inventories_locations', 'mx_quality_role')
            || ! Schema::hasTable('petfood_lot_genealogies')
            || ! Schema::hasTable('petfood_bom_versions')
        ) {
            Artisan::call('migrate', [
                '--path'  => 'plugins/xolocodigo/petfood/database/migrations',
                '--force' => true,
            ]);
        }

        // Forget per-request BOM-version grouping so each test starts clean.
        app(BomVersionService::class)->resetRequestState();
    }

    /**
     * A warehouse created via the normal flow (its created-hook builds the
     * internal locations). That hook references the global virtual locations
     * (supplier/customer/inventory/production), so we ensure they exist first.
     */
    public static function warehouse(): Warehouse
    {
        foreach ([LocationType::SUPPLIER, LocationType::CUSTOMER, LocationType::INVENTORY, LocationType::PRODUCTION] as $type) {
            if (! Location::where('type', $type)->exists()) {
                Location::factory()->create(['type' => $type]);
            }
        }

        return Warehouse::factory()->create([
            'company_id' => Company::query()->value('id'),
        ]);
    }

    /** A normal internal, non-scrap location (usable stock). */
    public static function internalLocation(): Location
    {
        return Location::factory()->create([
            'type'     => LocationType::INTERNAL,
            'is_scrap' => false,
        ]);
    }

    /**
     * A DRAFT manufacturing order with one raw-material move whose single line
     * consumes $consumedLot (or no lot, when null). The order is persisted with
     * saveQuietly() to skip the buggy Order::created -> computeFinishedMoves()
     * chain (upstream propagate_cancel). Move + line share the product UOM to
     * avoid the core UOM-category check. Mark the order DONE to trigger the
     * genealogy observer.
     */
    public static function rawMaterialOrder(Lot $producedLot, ?Lot $consumedLot, Product $consumedProduct, float $qty): Order
    {
        $order = Order::factory()->make([
            'producing_lot_id' => $producedLot->id,
            'product_id'       => $producedLot->product_id,
            'state'            => ManufacturingOrderState::DRAFT,
        ]);
        $order->saveQuietly();

        // raw_material_order_id is not fillable on Move; link it with a direct update.
        $move = Move::factory()->done()->create([
            'product_id' => $consumedProduct->id,
            'uom_id'     => $consumedProduct->uom_id,
        ]);
        DB::table('inventories_moves')->where('id', $move->id)->update([
            'raw_material_order_id' => $order->id,
        ]);

        MoveLine::factory()->done()->create([
            'move_id'    => $move->id,
            'product_id' => $consumedProduct->id,
            'uom_id'     => $consumedProduct->uom_id,
            'lot_id'     => $consumedLot?->id,
            'qty'        => $qty,
        ]);

        return $order->fresh();
    }

    /**
     * A DRAFT move whose source is a SUPPLIER location (i.e. a purchase receipt)
     * carrying $lot, attributed to $supplier. Mark the move DONE to trigger the
     * supplier-origin observer.
     */
    public static function supplierReceipt(?Partner $supplier, Product $product, Lot $lot): Move
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

    /** An empty bill of materials (recipe). Add components with bomLine(). */
    public static function billOfMaterial(): BillOfMaterial
    {
        $product = Product::factory()->create();

        return BillOfMaterial::factory()->create([
            'product_id' => $product->id,
            'uom_id'     => $product->uom_id,
        ]);
    }

    /**
     * Add a component line to a recipe. Creating the line fires the
     * BillOfMaterialLine observer, which versions the recipe.
     */
    public static function bomLine(BillOfMaterial $bom, Product $product, float $qty): BillOfMaterialLine
    {
        return BillOfMaterialLine::create([
            'bill_of_material_id' => $bom->id,
            'product_id'          => $product->id,
            'uom_id'              => $product->uom_id,
            'quantity'            => $qty,
        ]);
    }

    /** Simulate a request boundary for BOM-version grouping (see BomVersionService). */
    public static function newRequest(): void
    {
        app(BomVersionService::class)->resetRequestState();
    }

    /**
     * A DONE move whose destination is a CUSTOMER location (i.e. a delivery)
     * carrying $lot, attributed to $customer. This is what the closed recall
     * (affectedCustomers) reads — no observer involved, so it is left DONE.
     */
    public static function customerDelivery(?Partner $customer, Product $product, Lot $lot, float $qty): Move
    {
        $customerLocation = Location::factory()->create(['type' => LocationType::CUSTOMER]);

        $move = Move::factory()->create([
            'destination_location_id' => $customerLocation->id,
            'partner_id'              => $customer?->id,
            'product_id'              => $product->id,
            'uom_id'                  => $product->uom_id,
            'state'                   => MoveState::DRAFT,
        ]);

        MoveLine::factory()->done()->create([
            'move_id'    => $move->id,
            'product_id' => $product->id,
            'uom_id'     => $product->uom_id,
            'lot_id'     => $lot->id,
            'qty'        => $qty,
        ]);

        $move->update(['state' => MoveState::DONE]);

        return $move->fresh();
    }

    /**
     * Stock of a product/lot at a location, without firing an inventory
     * adjustment (inventory_diff_quantity = 0).
     */
    public static function stock(Product $product, Location $location, ?Lot $lot, float $qty): ProductQuantity
    {
        return ProductQuantity::create([
            'product_id'              => $product->id,
            'location_id'             => $location->id,
            'lot_id'                  => $lot?->id,
            'quantity'                => $qty,
            'reserved_quantity'       => 0,
            'inventory_diff_quantity' => 0,
        ]);
    }
}
