<?php

namespace XoloCodigo\PetFood\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Activate MXN (Peso Mexicano) currency.
 *
 * The upstream Webkul\Support\Database\Seeders\CurrencySeeder loads
 * currencies from plugins/webkul/security/src/Data/currencies.json,
 * where MXN exists but is seeded with active=false. For a Mexican SME
 * operating with MXN, the currency must be active so company defaults,
 * partner balances, and price lists can use it without manual toggling.
 *
 * Run after the upstream currency seeder. Idempotent.
 */
class MxnCurrencySeeder extends Seeder
{
    public function run(): void
    {
        $affected = DB::table('currencies')
            ->where('code', 'MXN')
            ->update(['active' => true, 'updated_at' => now()]);

        if ($affected === 0) {
            $this->command?->warn('MxnCurrencySeeder: MXN currency row not found. Run the upstream CurrencySeeder first.');
        } else {
            $this->command?->info('MxnCurrencySeeder: MXN activated.');
        }
    }
}
