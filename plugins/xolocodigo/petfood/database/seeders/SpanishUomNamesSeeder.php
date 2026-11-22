<?php

namespace XoloCodigo\PetFood\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Rename the upstream units of measure seeded with English names to Spanish.
 *
 * The upstream UOMSeeder names a few units in English, and the plant sees them
 * on products, manufacturing orders and recipe change history. The core finds
 * "Units" by name only as a default with a fallback to the first unit
 * (ManufacturingOrderForm::getDefaultUomId), which is this same row.
 *
 * Run after the upstream UOM seeder. Idempotent.
 */
class SpanishUomNamesSeeder extends Seeder
{
    private const NAMES = ['Units' => 'Unidades', 'Dozens' => 'Docenas', 'Days' => 'Días', 'Hours' => 'Horas'];

    public function run(): void
    {
        foreach (self::NAMES as $english => $spanish) {
            DB::table('unit_of_measures')->where('name', $english)->update(['name' => $spanish, 'updated_at' => now()]);
        }
    }
}
