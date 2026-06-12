<?php

namespace XoloCodigo\PetFood\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Inventory\Models\Lot;
use Webkul\Manufacturing\Models\Order;
use Webkul\Product\Models\Product;
use XoloCodigo\PetFood\Traceability\Models\LotGenealogy;

/**
 * @extends Factory<LotGenealogy>
 */
class LotGenealogyFactory extends Factory
{
    protected $model = LotGenealogy::class;

    public function definition(): array
    {
        return [
            'manufacturing_order_id' => Order::factory(),
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
