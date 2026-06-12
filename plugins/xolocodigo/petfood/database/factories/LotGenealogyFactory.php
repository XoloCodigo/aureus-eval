<?php

namespace XoloCodigo\PetFood\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Webkul\Inventory\Models\Location;
use Webkul\Inventory\Models\Lot;
use Webkul\Inventory\Models\OperationType;
use Webkul\Manufacturing\Enums\BillOfMaterialConsumption;
use Webkul\Manufacturing\Enums\ManufacturingOrderPriority;
use Webkul\Manufacturing\Enums\ManufacturingOrderState;
use Webkul\Manufacturing\Models\BillOfMaterial;
use Webkul\Product\Models\Product;
use Webkul\Support\Models\Company;
use Webkul\Support\Models\UOM;
use XoloCodigo\PetFood\Traceability\Models\LotGenealogy;

/**
 * @extends Factory<LotGenealogy>
 */
class LotGenealogyFactory extends Factory
{
    protected $model = LotGenealogy::class;

    public function definition(): array
    {
        // Use the first seeded UOM so that Order, BOM, and Move all share the
        // same UOM category — prevents the "UOM category mismatch" exception
        // thrown by Move::computeProductQty() during factory creation.
        $sharedUom = UOM::query()->first() ?? UOM::factory()->create();

        // Create all Order dependencies normally (so their hooks fire correctly,
        // e.g. Company::creating that auto-sets partner_id).
        $product = Product::factory()->create(['uom_id' => $sharedUom->id, 'uom_po_id' => $sharedUom->id]);
        $company = Company::query()->value('id')
            ? Company::first()
            : Company::factory()->create();
        $operationType = OperationType::query()->first();
        $sourceLocation = Location::query()->first();
        $bom = BillOfMaterial::factory()->create([
            'product_id' => $product->id,
            'uom_id'     => $sharedUom->id,
        ]);

        // Raw insert into manufacturing_orders to bypass Order model boot hooks.
        // The Order::created hook calls computeFinishedMoves() which hits an
        // upstream bug: propagate_cancel is passed to finishedMoves()->create()
        // but the column does not exist in inventories_moves.
        $orderId = DB::table('manufacturing_orders')->insertGetId([
            'reference'               => strtoupper(fake()->bothify('MO-######')),
            'priority'                => ManufacturingOrderPriority::NORMAL->value,
            'state'                   => ManufacturingOrderState::DRAFT->value,
            'consumption'             => BillOfMaterialConsumption::WARNING->value,
            'quantity'                => fake()->randomFloat(4, 1, 50),
            'quantity_producing'      => 0,
            'product_uom_qty'         => 0,
            'started_at'              => now(),
            'is_planned'              => 0,
            'is_locked'               => 0,
            'product_id'              => $product->id,
            'uom_id'                  => $sharedUom->id,
            'operation_type_id'       => $operationType?->id ?? OperationType::factory()->create()->id,
            'source_location_id'      => $sourceLocation?->id ?? Location::factory()->create()->id,
            'destination_location_id' => $sourceLocation?->id ?? Location::query()->value('id'),
            'bill_of_material_id'     => $bom->id,
            'company_id'              => $company->id,
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);

        return [
            'manufacturing_order_id' => $orderId,
            'produced_lot_id'        => Lot::factory(),
            'produced_product_id'    => Product::factory(),
            'consumed_lot_id'        => Lot::factory(),
            'consumed_product_id'    => Product::factory(),
            'quantity'               => fake()->randomFloat(4, 1, 100),
            'uom_id'                 => null,
            'consumed_move_line_id'  => null,
            'company_id'             => null,
        ];
    }
}
