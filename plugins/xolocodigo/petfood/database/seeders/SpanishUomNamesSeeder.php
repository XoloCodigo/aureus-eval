<?php

namespace XoloCodigo\PetFood\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Rename the upstream "Units" unit of measure to "Unidades".
 *
 * The upstream UOMSeeder names the reference count unit in English, and the
 * plant sees it on products, manufacturing orders and recipe change history.
 * The core finds it by name only as a default with a fallback to the first unit
 * (ManufacturingOrderForm::getDefaultUomId), which is this same row.
 *
 * Run after the upstream UOM seeder. Idempotent.
 */
class UnidadesUomSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('unit_of_measures')->where('name', 'Units')->update(['name' => 'Unidades', 'updated_at' => now()]);
    }
}
