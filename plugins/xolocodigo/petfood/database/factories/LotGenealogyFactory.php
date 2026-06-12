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
        // make() crea las dependencias del Order (Company, Product, BOM...) CON sus
        // eventos (p. ej. Company::creating que setea partner_id), y saveQuietly()
        // persiste solo el Order sin disparar el hook Order::created -> ... ->
        // computeFinishedMoves(), que choca con un bug de upstream (la columna
        // propagate_cancel existe en inventories_rules pero no en inventories_moves).
        // No se puede usar createQuietly() porque es withoutEvents() global y
        // tumbaría también los hooks de las dependencias.
        $order = Order::factory()->make();
        $order->saveQuietly();

        return [
            'manufacturing_order_id' => $order->id,
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
