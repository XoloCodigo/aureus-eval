<?php

use Illuminate\Support\Facades\DB;
use XoloCodigo\PetFood\Database\Seeders\UnidadesUomSeeder;

require_once __DIR__.'/../Support/PetFoodScenario.php';

beforeEach(fn () => PetFoodScenario::bootstrap());

/*
 * The core counts things in a unit seeded as "Units", which every screen and
 * the recipe change history show to a Spanish-speaking plant.
 */
it('renames the core "Units" unit of measure to "Unidades"', function () {
    $units = DB::table('unit_of_measures')->whereIn('name', ['Units', 'Unidades'])->value('id');
    DB::table('unit_of_measures')->where('id', $units)->update(['name' => 'Units']);

    (new UnidadesUomSeeder)->run();

    expect(DB::table('unit_of_measures')->where('id', $units)->value('name'))->toBe('Unidades');
});
