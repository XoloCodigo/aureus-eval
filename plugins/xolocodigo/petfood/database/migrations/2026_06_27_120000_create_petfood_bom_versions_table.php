<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable versions of a bill of materials (recipe). Aureus edits a BOM in
 * place, losing history. Here, whenever a recipe's lines change, the previous
 * version is sealed (effective_to set) and a new one opened, so we can answer
 * "which formula was in effect on date X?" and keep a full audit trail of who
 * changed it. The recipe lines are stored as a JSON snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petfood_bom_versions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bill_of_material_id')
                ->constrained('manufacturing_bills_of_materials')
                ->cascadeOnDelete();

            $table->unsignedInteger('version');
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();

            // [{product_id, quantity, uom_id}, ...] sorted by product_id.
            $table->json('recipe');

            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['bill_of_material_id', 'version']);
            $table->index(['bill_of_material_id', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petfood_bom_versions');
    }
};
