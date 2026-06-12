<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Genealogía de lotes: vincula el lote producido (PT/WIP) con cada lote
 * de materia prima consumido en una orden de fabricación. Permite recall
 * dirigido (dado un lote MP, ¿qué PT lo usaron?) y trazabilidad inversa.
 * Reusa los lotes y move_lines del módulo inventories del core.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petfood_lot_genealogies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('manufacturing_order_id')
                ->constrained('manufacturing_orders')
                ->cascadeOnDelete();

            $table->foreignId('produced_lot_id')
                ->constrained('inventories_lots')
                ->cascadeOnDelete();

            $table->foreignId('produced_product_id')
                ->constrained('products_products')
                ->cascadeOnDelete();

            $table->foreignId('consumed_lot_id')
                ->constrained('inventories_lots')
                ->cascadeOnDelete();

            $table->foreignId('consumed_product_id')
                ->constrained('products_products')
                ->cascadeOnDelete();

            $table->decimal('quantity', 15, 4)->default(0);

            $table->foreignId('uom_id')
                ->nullable()
                ->constrained('unit_of_measures')
                ->nullOnDelete();

            $table->foreignId('consumed_move_line_id')
                ->nullable()
                ->constrained('inventories_move_lines')
                ->nullOnDelete();

            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('produced_lot_id');
            $table->index('consumed_lot_id');
            $table->index('manufacturing_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petfood_lot_genealogies');
    }
};
