<?php

namespace XoloCodigo\PetFood\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Mexican market base data. Order matters: each seeder assumes the
        // upstream Webkul seeders have already populated their base tables.
        $this->call([
            MxnCurrencySeeder::class,
            LftEmploymentTypeSeeder::class,
        ]);
    }
}
