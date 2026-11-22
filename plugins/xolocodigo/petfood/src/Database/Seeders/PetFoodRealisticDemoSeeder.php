<?php

namespace XoloCodigo\PetFood\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Inventory\Enums\OperationType as OperationTypeKind;
use Webkul\Inventory\Models\Lot;
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
use XoloCodigo\PetFood\Quality\Enums\MxQualityRole;
use XoloCodigo\PetFood\Quality\Services\QualityLocationService;
use XoloCodigo\PetFood\Recipes\Services\BomVersionService;

/**
 * Plant-scale petfood demo on top of PetFoodDemoSeeder (which it leaves untouched):
 * 14 raw materials with supplier lots and expiry as opening stock, an in-house
 * vitamin-mineral premix (no stock: produced live to show the 2-level cascade) and
 * three kibble recipes per tonne. Spec: docs/superpowers/specs/2026-11-18-petfood-demo-realista-design.md
 */
class PetFoodRealisticDemoSeeder extends Seeder
{
    public const MARKER = 'Croqueta Perro Adulto Premium 20 kg';

    private const PREMIX = 'Premezcla vitamínica-mineral';

    /** @var array<string, string> supplier code => name */
    private const SUPPLIERS = [
        'GN' => 'Granos del Norte (demo)',
        'AG' => 'Agroinsumos del Golfo',
        'PB' => 'Proteínas del Bajío',
        'AO' => 'Aceites y Grasas de Occidente',
        'NA' => 'Nutrición Animal Integral',
    ];

    private const CUSTOMERS = ['Forrajera San Miguel', 'Pet Shop La Huella'];

    /**
     * Lots: [quantity, location ('stock'|'quarantine'), expiry in days (null = shelf life)].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: float, 4: string, 5: int, 6: list<array{0: int, 1?: string, 2?: int}>}>
     */
    private const RAW_MATERIALS = [
        'Maíz amarillo'               => ['MZ', 'kg', 't', 6.20, 'GN', 6, [[25000], [18000], [10000, 'quarantine']]],
        'Sorgo'                       => ['SG', 'kg', 't', 5.60, 'GN', 6, [[15000], [10000]]],
        'Pasta de soya 46%'           => ['PS', 'kg', 't', 11.50, 'AG', 6, [[12000], [8000]]],
        'Harina de carne y hueso 50%' => ['HC', 'kg', 't', 14.00, 'PB', 6, [[6000], [4500]]],
        'Harina de pescado 60%'       => ['HP', 'kg', 't', 28.00, 'PB', 6, [[3000], [2000]]],
        'Salvado de trigo'            => ['SV', 'kg', 't', 4.50, 'AG', 6, [[4000]]],
        'Aceite de pollo'             => ['AC', 'L', 'L', 32.00, 'AO', 4, [[8000], [5000]]],
        'Sal refinada'                => ['SL', 'kg', 'kg', 4.00, 'NA', 12, [[1000], [500]]],
        'Carbonato de calcio'         => ['CC', 'kg', 'kg', 3.00, 'NA', 12, [[2000]]],
        'Fosfato dicálcico'           => ['FD', 'kg', 'kg', 18.00, 'NA', 12, [[1200]]],
        'Núcleo vitamínico'           => ['NV', 'kg', 'kg', 180.00, 'NA', 12, [[150, 'stock', 20], [100]]],
        'Minerales traza'             => ['MT', 'kg', 'kg', 95.00, 'NA', 12, [[120]]],
        'Antioxidante'                => ['AX', 'kg', 'kg', 210.00, 'NA', 12, [[40]]],
        'Taurina'                     => ['TA', 'kg', 'kg', 520.00, 'NA', 12, [[25]]],
    ];

    /** @var array<string, array{0: string, 1: float}> finished good => [uom, price per bag] */
    private const FINISHED_GOODS = [
        self::MARKER                    => ['Unidades', 780.00],
        'Croqueta Perro Cachorro 20 kg' => ['Unidades', 860.00],
        'Croqueta Gato Adulto 10 kg'    => ['Unidades', 640.00],
    ];

    /** @var array<string, array{0: string, 1: int, 2: array<string, float>}> product => [code, batch qty, ingredients] */
    private const RECIPES = [
        self::PREMIX => ['BOM-PREMEZCLA', 100, [
            'Carbonato de calcio' => 40, 'Sal refinada' => 25, 'Fosfato dicálcico' => 20,
            'Núcleo vitamínico'   => 8, 'Minerales traza' => 6, 'Antioxidante' => 1,
        ]],
        self::MARKER => ['BOM-PERRO-ADU', 50, [
            'Maíz amarillo'   => 380, 'Pasta de soya 46%' => 180, 'Harina de carne y hueso 50%' => 150, 'Sorgo' => 120,
            'Aceite de pollo' => 60, 'Salvado de trigo' => 50, 'Harina de pescado 60%' => 40, self::PREMIX => 20,
        ]],
        'Croqueta Perro Cachorro 20 kg' => ['BOM-PERRO-CAC', 50, [
            'Maíz amarillo'         => 330, 'Pasta de soya 46%' => 200, 'Harina de carne y hueso 50%' => 160, 'Sorgo' => 115,
            'Harina de pescado 60%' => 80, 'Aceite de pollo' => 80, self::PREMIX => 25, 'Fosfato dicálcico' => 10,
        ]],
        'Croqueta Gato Adulto 10 kg' => ['BOM-GATO-ADU', 100, [
            'Maíz amarillo' => 280, 'Harina de carne y hueso 50%' => 180, 'Pasta de soya 46%' => 160, 'Harina de pescado 60%' => 120,
            'Sorgo'         => 90, 'Aceite de pollo' => 90, 'Salvado de trigo' => 50, self::PREMIX => 25, 'Sal refinada' => 3, 'Taurina' => 2,
        ]],
    ];

    private int $companyId;

    private ?int $userId;

    public function run(): void
    {
        if (Product::query()->where('name', self::MARKER)->exists()) {
            $this->command?->warn('Demo realista ya sembrado; se omite.');

            return;
        }

        DB::transaction(fn () => $this->seed());

        $this->command?->info('Demo realista sembrado: 14 MP con lotes, premezcla y 3 croquetas con receta.');
    }

    private function seed(): void
    {
        $this->companyId = Company::query()->firstOrFail()->id;
        $this->userId = User::query()->value('id');
        $warehouse = Warehouse::query()->firstOrFail();
        $locations = [
            'stock'      => $warehouse->lot_stock_location_id,
            'quarantine' => app(QualityLocationService::class)->ensureFor($warehouse)
                ->firstWhere('mx_quality_role', MxQualityRole::Quarantine->value)->id,
        ];
        $this->call(UnidadesUomSeeder::class);
        $uoms = UOM::query()->whereIn('name', ['kg', 't', 'L', 'Unidades'])->pluck('id', 'name');

        $root = $this->category('Alimento para mascotas');
        $categories = [
            'raw_material'  => $this->category('Materias primas', $root),
            'wip'           => $this->category('Premezclas', $root),
            'finished_good' => $this->category('Producto terminado', $root),
        ];

        $suppliers = collect(self::SUPPLIERS)->map(fn (string $name): Partner => $this->partner($name, 'supplier_rank'));
        collect(self::CUSTOMERS)->each(fn (string $name): Partner => $this->partner($name, 'customer_rank'));

        $products = [];
        $supplierLot = 300;

        foreach (self::RAW_MATERIALS as $name => [$code, $uom, $poUom, $cost, $supplier, $shelfMonths, $lots]) {
            $product = $products[$name] = $this->product($name, 'raw_material', $uoms[$uom], $uoms[$poUom], $cost, 0, $categories);

            foreach ($lots as $index => $lot) {
                [$quantity, $where, $expiryDays] = $lot + [1 => 'stock', 2 => null];

                $this->stock($product, sprintf('%s-2611-%02d', $code, $index + 1), $suppliers[$supplier],
                    sprintf('%s-2026-%04d', $supplier, ++$supplierLot), $quantity, $locations[$where],
                    $expiryDays ? now()->addDays($expiryDays) : now()->addMonths($shelfMonths));
            }
        }

        $products[self::PREMIX] = $this->product(self::PREMIX, 'wip', $uoms['kg'], $uoms['kg'], 0, 0, $categories);

        foreach (self::FINISHED_GOODS as $name => [$uom, $price]) {
            $products[$name] = $this->product($name, 'finished_good', $uoms[$uom], $uoms[$uom], 0, $price, $categories);
        }

        $operationTypeId = OperationType::query()
            ->where('type', OperationTypeKind::MANUFACTURE)
            ->where('warehouse_id', $warehouse->id)
            ->firstOrFail()->id;

        foreach (self::RECIPES as $name => [$code, $batch, $ingredients]) {
            $this->recipe($code, $products[$name], $batch, $ingredients, $products, $operationTypeId);
        }
    }

    private function category(string $name, ?Category $parent = null): Category
    {
        return Category::query()->create(['name' => $name, 'parent_id' => $parent?->id, 'creator_id' => $this->userId]);
    }

    /**
     * The rank (vendor/customer list filters) is a column of the optional accounts plugin.
     */
    private function partner(string $name, string $rank): Partner
    {
        $partner = Partner::query()->firstOrCreate(['name' => $name], [
            'account_type' => 'company',
            'sub_type'     => 'partner',
            'company_id'   => $this->companyId,
            'creator_id'   => $this->userId,
            'user_id'      => $this->userId,
        ]);

        if (Schema::hasColumn($partner->getTable(), $rank)) {
            $partner->forceFill([$rank => 1])->save();
        }

        return $partner;
    }

    /**
     * @param  array<string, Category>  $categories
     */
    private function product(string $name, string $itemType, int $uomId, int $poUomId, float $cost, float $price, array $categories): Product
    {
        $product = Product::query()->create([
            'type'         => 'goods',
            'name'         => $name,
            'price'        => $price,
            'cost'         => $cost,
            'enable_sales' => $itemType === 'finished_good',
            'is_storable'  => true,
            'tracking'     => 'lot',
            'category_id'  => $categories[$itemType]->id,
            'uom_id'       => $uomId,
            'uom_po_id'    => $poUomId,
            'company_id'   => $this->companyId,
            'creator_id'   => $this->userId,
        ]);

        // Columns added by the inventory plugin / ours, outside the core $fillable.
        $product->forceFill(['mx_item_type' => $itemType, 'use_expiration_date' => true])->save();

        return $product;
    }

    private function stock(Product $product, string $lotName, Partner $supplier, string $supplierLot, int $quantity, int $locationId, mixed $expiresAt): void
    {
        $lot = Lot::query()->create([
            'name'            => $lotName,
            'product_id'      => $product->id,
            'uom_id'          => $product->uom_id,
            'location_id'     => $locationId,
            'expiration_date' => $expiresAt,
            'company_id'      => $this->companyId,
            'creator_id'      => $this->userId,
        ]);
        $lot->forceFill(['mx_supplier_id' => $supplier->id, 'mx_supplier_lot' => $supplierLot])->save();

        ProductQuantity::query()->create([
            'product_id'              => $product->id,
            'location_id'             => $locationId,
            'lot_id'                  => $lot->id,
            'quantity'                => $quantity,
            'reserved_quantity'       => 0,
            'inventory_diff_quantity' => 0,
            'company_id'              => $this->companyId,
            'creator_id'              => $this->userId,
        ]);
    }

    /**
     * @param  array<string, float>  $ingredients
     * @param  array<string, Product>  $products
     */
    private function recipe(string $code, Product $product, int $batch, array $ingredients, array $products, int $operationTypeId): void
    {
        app(BomVersionService::class)->resetRequestState();

        $bom = BillOfMaterial::query()->create([
            'code'              => $code,
            'type'              => BillOfMaterialType::NORMAL,
            'ready_to_produce'  => BillOfMaterialReadyToProduce::ALL_AVAILABLE,
            'consumption'       => BillOfMaterialConsumption::WARNING,
            'quantity'          => $batch,
            'product_id'        => $product->id,
            'uom_id'            => $product->uom_id,
            'operation_type_id' => $operationTypeId,
            'company_id'        => $this->companyId,
            'creator_id'        => $this->userId,
        ]);

        $sort = 0;

        foreach ($ingredients as $ingredient => $quantity) {
            BillOfMaterialLine::query()->create([
                'sort'                => ++$sort,
                'quantity'            => $quantity,
                'bill_of_material_id' => $bom->id,
                'product_id'          => $products[$ingredient]->id,
                'uom_id'              => $products[$ingredient]->uom_id,
                'company_id'          => $this->companyId,
                'creator_id'          => $this->userId,
            ]);
        }

        // Unit cost rolled up from the recipe (premix first, so the kibbles include it).
        $product->update(['cost' => $bom->getComponentCost(1)]);
    }
}
