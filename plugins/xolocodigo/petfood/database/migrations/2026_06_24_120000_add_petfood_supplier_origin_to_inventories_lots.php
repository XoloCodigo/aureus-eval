<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capture the origin of an internal lot: which supplier it came from and,
 * eventually, the supplier's own lot number.
 *
 * In the petfood flow, Quality assigns an INTERNAL lot to incoming raw
 * material (distinct from the supplier's lot). Regulatory traceability
 * (SADER) requires proving "which raw material from which supplier" went
 * into each finished good, so we record the supplier directly on the lot
 * rather than relying on a fragile query through stock move lines.
 *
 *  - mx_supplier_id  — the partner (supplier) that delivered the material,
 *    populated automatically when a purchase receipt is validated.
 *  - mx_supplier_lot — the supplier's own lot number, kept nullable until
 *    the client confirms whether and where they capture it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories_lots', function (Blueprint $table) {
            $table->foreignId('mx_supplier_id')
                ->nullable()
                ->constrained('partners_partners')
                ->nullOnDelete();

            $table->string('mx_supplier_lot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventories_lots', function (Blueprint $table) {
            if (Schema::hasColumn('inventories_lots', 'mx_supplier_id')) {
                $table->dropConstrainedForeignId('mx_supplier_id');
            }

            if (Schema::hasColumn('inventories_lots', 'mx_supplier_lot')) {
                $table->dropColumn('mx_supplier_lot');
            }
        });
    }
};
