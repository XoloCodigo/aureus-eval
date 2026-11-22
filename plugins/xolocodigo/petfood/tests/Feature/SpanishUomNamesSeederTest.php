<?php

use Illuminate\Support\Facades\DB;
use XoloCodigo\PetFood\Database\Seeders\SpanishUomNamesSeeder;

require_once __DIR__.'/../Support/PetFoodScenario.php';

beforeEach(fn () => PetFoodScenario::bootstrap());

/*
 * The core seeds a few units of measure with English names, which every screen
 * and the recipe change history show to a Spanish-speaking plant.
 */
it('renames the English core units of measure to Spanish', function () {
    $spanish = ['Units' => 'Unidades', 'Dozens' => 'Docenas', 'Days' => 'Días', 'Hours' => 'Horas'];
    foreach ($spanish as $english => $name) {
        DB::table('unit_of_measures')->where('name', $name)->update(['name' => $english]);
    }

    (new SpanishUomNamesSeeder)->run();

    expect(DB::table('unit_of_measures')->whereIn('name', array_keys($spanish))->pluck('name')->all())->toBe([])
        ->and(DB::table('unit_of_measures')->whereIn('name', $spanish)->pluck('name')->all())->toEqualCanonicalizing(array_values($spanish));
});
