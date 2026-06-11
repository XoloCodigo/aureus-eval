<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add petfood-industry taxonomy columns to products_products.
 *
 * Adds two columns to the upstream products_products table, with the mx_
 * prefix to guarantee zero collision with future upstream additions:
 *
 *  - mx_item_type — MP/WIP/PT/Consumable, orthogonal to Aureus's existing
 *    `type` field (goods/service/consumable). See XoloCodigo\PetFood\Enums\
 *    MxItemType for the value set.
 *
 *  - mx_requires_quality_inspection — flag for the QC gate. When true,
 *    receipts of this product (purchase or manufacture) automatically
 *    route to a quarantine location and cannot be used or sold until a
 *    Quality Inspection releases them.
 *
 * NOTE: We deliberately do NOT add columns that Aureus's inventories
 * plugin already provides:
 *   - tracking (serial/lot/qty) — already covers batch tracking
 *   - expiration_time, use_time, removal_time, alert_time — already cover
 *     shelf-life calculations
 *   - use_expiration_date — already covers the expiration toggle
 *   - is_storable — already covers physical vs non-physical
 *
 * Reuse those existing columns from inventories instead of duplicating.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products_products', function (Blueprint $table) {
            $table->string('mx_item_type', 16)->nullable();
            $table->boolean('mx_requires_quality_inspection')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('products_products', function (Blueprint $table) {
            if (Schema::hasColumn('products_products', 'mx_requires_quality_inspection')) {
                $table->dropColumn('mx_requires_quality_inspection');
            }

            if (Schema::hasColumn('products_products', 'mx_item_type')) {
                $table->dropColumn('mx_item_type');
            }
        });
    }
};
