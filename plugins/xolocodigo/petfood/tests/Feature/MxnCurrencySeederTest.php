<?php

use Webkul\Support\Models\Currency;
use XoloCodigo\PetFood\Database\Seeders\MxnCurrencySeeder;

require_once __DIR__.'/../Support/PetFoodScenario.php';

beforeEach(fn () => PetFoodScenario::bootstrap());

/*
 * `currencies` keeps the ISO code in `name` (there is no `code` column), and
 * since the 2026-09 sync money display and sale/purchase pricing resolve the
 * currency through it, so an inactive MXN is visible to the plant.
 */
it('activates MXN, whose ISO code lives in the name column', function () {
    Currency::query()->updateOrCreate(['name' => 'MXN'], ['full_name' => 'Peso Mexicano', 'active' => false]);

    (new MxnCurrencySeeder)->run();

    expect(Currency::query()->where('name', 'MXN')->value('active'))->toBeTruthy();
});
