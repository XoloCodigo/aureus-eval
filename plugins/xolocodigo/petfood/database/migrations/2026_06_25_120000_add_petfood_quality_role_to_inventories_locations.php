<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks an inventory location as a quality area: quarantine, rejected or waste.
 *
 * The petfood flow keeps non-conforming or pending material in a physical area
 * so it cannot be used or sold. Aureus already excludes is_scrap locations from
 * reservation and from a lot's available quantity, so quality locations are
 * created with is_scrap = true; this column only distinguishes their ROLE
 * (quarantine vs rejected vs waste) for the petfood flow, reports and UI.
 *
 * Plain nullable string on purpose — no DB-level FK or index — to stay
 * SQLite-friendly when altering a core table (see S2.1 lesson).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories_locations', function (Blueprint $table) {
            $table->string('mx_quality_role')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventories_locations', function (Blueprint $table) {
            if (Schema::hasColumn('inventories_locations', 'mx_quality_role')) {
                $table->dropColumn('mx_quality_role');
            }
        });
    }
};
