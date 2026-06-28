<?php

namespace XoloCodigo\PetFood\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Webkul\Inventory\Enums\LocationType;
use Webkul\Inventory\Enums\MoveState;
use Webkul\Inventory\Models\Location;
use Webkul\Inventory\Models\Lot;
use Webkul\Inventory\Models\Move;
use Webkul\Inventory\Models\MoveLine;
use Webkul\Inventory\Models\OperationType;
use Webkul\Inventory\Models\ProductQuantity;
use Webkul\Inventory\Models\Warehouse;
use Webkul\Manufacturing\Enums\BillOfMaterialConsumption;
use Webkul\Manufacturing\Enums\BillOfMaterialReadyToProduce;
use Webkul\Manufacturing\Enums\BillOfMaterialType;
use Webkul\Manufacturing\Models\BillOfMaterial;
use Webkul\Manufacturing\Models\BillOfMaterialLine;
use Webkul\Partner\Models\Partner;
use Webkul\Product\Models\Category;
use Webkul\Product\Models\Product;
use Webkul\Security\Models\User;
use Webkul\Support\Models\Company;
use Webkul\Support\Models\UOM;
use XoloCodigo\PetFood\Quality\Services\QualityLocationService;
use XoloCodigo\PetFood\Recipes\Services\BomVersionService;
use XoloCodigo\PetFood\Traceability\Models\LotGenealogy;

/**
 * Seeds a coherent end-to-end petfood demo on top of an installed ERP, so the
 * Calidad e Inocuidad pages show real data: a raw material received from a
 * supplier, manufactured into a finished good (genealogy), shipped to a
 * customer (closed recall), with a versioned recipe and quarantined stock.
 *
 * Idempotent (skips if already seeded) and uses create() — no factories — so it
 * runs on the production server (composer --no-dev). The manufacturing order is
 * inserted directly to bypass the heavy created-hook chain that needs a fully
 * configured warehouse (not a real bug — see project-s2 memory).
 */
class PetFoodDemoSeeder extends Seeder
{
    private const PT_NAME = 'Croqueta Adulto 20kg (demo)';

    public function run(): void
    {
        if (Product::query()->where('name', self::PT_NAME)->exists()) {
            $this->command?->warn('Demo petfood ya sembrado; se omite.');

            return;
        }

        $company = Company::query()->firstOrFail();
        $user = User::query()->value('id');
        $uom = UOM::query()->firstOrFail();
        $category = Category::query()->first();
        $warehouse = Warehouse::query()->firstOrFail();
        $operationType = OperationType::query()->firstOrFail();
        $internal = Location::query()->where('type', LocationType::INTERNAL)->firstOrFail();

        $supplier = $this->partner('Granos del Norte (demo)', $company->id, $user);
        $customer = $this->partner('Distribuidora Patitas (demo)', $company->id, $user);

        $mp = $this->product('Harina de pollo (demo)', 'raw_material', $uom, $category, $company->id, $user);
        $pt = $this->product(self::PT_NAME, 'finished_good', $uom, $category, $company->id, $user);

        $mpLot = $this->lot('MP-POLLO-2606', $mp, $uom->id, $company->id, $user);
        $mpLot->mx_supplier_id = $supplier->id;
        $mpLot->mx_supplier_lot = 'GN-2026-0455';
        $mpLot->save();

        $ptLot = $this->lot('PT-CROQ-2606', $pt, $uom->id, $company->id, $user);

        // Manufacturing order (direct insert — bypasses the created-hook chain).
        $orderId = DB::table('manufacturing_orders')->insertGetId([
            'name'                    => 'MO/DEMO-001',
            'state'                   => 'done',
            'consumption'             => BillOfMaterialConsumption::WARNING->value,
            'quantity'                => 100,
            'product_id'              => $pt->id,
            'uom_id'                  => $uom->id,
            'producing_lot_id'        => $ptLot->id,
            'operation_type_id'       => $operationType->id,
            'source_location_id'      => $internal->id,
            'destination_location_id' => $internal->id,
            'company_id'              => $company->id,
            'started_at'              => now(),
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);

        LotGenealogy::query()->create([
            'manufacturing_order_id' => $orderId,
            'produced_lot_id'        => $ptLot->id,
            'produced_product_id'    => $pt->id,
            'consumed_lot_id'        => $mpLot->id,
            'consumed_product_id'    => $mp->id,
            'quantity'               => 60,
            'uom_id'                 => $uom->id,
            'company_id'             => $company->id,
        ]);

        // Customer delivery of the PT lot -> closed recall.
        $customerLoc = Location::query()->where('type', LocationType::CUSTOMER)->first()
            ?? Location::query()->create([
                'name'       => 'Clientes (demo)',
                'type'       => LocationType::CUSTOMER,
                'company_id' => $company->id,
            ]);

        $move = Move::query()->create([
            'name'                    => 'Entrega demo',
            'source_location_id'      => $internal->id,
            'destination_location_id' => $customerLoc->id,
            'partner_id'              => $customer->id,
            'product_id'              => $pt->id,
            'uom_id'                  => $uom->id,
            'state'                   => MoveState::DONE,
            'company_id'              => $company->id,
        ]);
        MoveLine::query()->create([
            'move_id'    => $move->id,
            'product_id' => $pt->id,
            'uom_id'     => $uom->id,
            'lot_id'     => $ptLot->id,
            'qty'        => 40,
            'state'      => MoveState::DONE,
        ]);

        // Recipe + change history (v1 -> v2).
        $bom = BillOfMaterial::query()->create([
            'code'              => 'BOM-CROQ-001',
            'type'              => BillOfMaterialType::NORMAL,
            'ready_to_produce'  => BillOfMaterialReadyToProduce::ALL_AVAILABLE,
            'consumption'       => BillOfMaterialConsumption::WARNING,
            'quantity'          => 100,
            'product_id'        => $pt->id,
            'uom_id'            => $uom->id,
            'operation_type_id' => $operationType->id,
            'company_id'        => $company->id,
        ]);

        $versions = app(BomVersionService::class);
        $versions->resetRequestState();
        $line = BillOfMaterialLine::query()->create([
            'bill_of_material_id' => $bom->id,
            'product_id'          => $mp->id,
            'uom_id'              => $uom->id,
            'quantity'            => 60,
        ]);
        $versions->resetRequestState(); // simulate a later edit -> new version
        $line->update(['quantity' => 65]);

        // Quality areas + retained stock in quarantine.
        $quarantine = app(QualityLocationService::class)->ensureFor($warehouse)
            ->firstWhere('mx_quality_role', 'quarantine');
        if ($quarantine) {
            ProductQuantity::query()->create([
                'product_id'              => $mp->id,
                'location_id'             => $quarantine->id,
                'lot_id'                  => $mpLot->id,
                'quantity'                => 15,
                'reserved_quantity'       => 0,
                'inventory_diff_quantity' => 0,
            ]);
        }

        $this->command?->info('Demo petfood sembrado: MP/PT + proveedor, orden + genealogía, entrega a cliente, receta v1→v2, cuarentena.');
    }

    private function partner(string $name, int $companyId, ?int $userId): Partner
    {
        return Partner::query()->create([
            'account_type' => 'individual',
            'sub_type'     => 'partner',
            'name'         => $name,
            'company_id'   => $companyId,
            'creator_id'   => $userId,
            'user_id'      => $userId,
        ]);
    }

    private function product(string $name, string $itemType, UOM $uom, ?Category $category, int $companyId, ?int $userId): Product
    {
        $product = Product::query()->create([
            'type'        => 'goods',
            'name'        => $name,
            'price'       => 0,
            'cost'        => 0,
            'enable_sales' => true,
            'category_id' => $category?->id,
            'uom_id'      => $uom->id,
            'uom_po_id'   => $uom->id,
            'company_id'  => $companyId,
            'creator_id'  => $userId,
        ]);
        $product->mx_item_type = $itemType;
        $product->save();

        return $product;
    }

    private function lot(string $name, Product $product, int $uomId, int $companyId, ?int $userId): Lot
    {
        return Lot::query()->create([
            'name'       => $name,
            'product_id' => $product->id,
            'uom_id'     => $uomId,
            'company_id' => $companyId,
            'creator_id' => $userId,
        ]);
    }
}
