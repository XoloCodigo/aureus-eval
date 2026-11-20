<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Webkul\Inventory\Models\Lot;
use Webkul\Partner\Models\Partner;
use Webkul\Product\Models\Product;
use Webkul\Security\Models\User;
use Webkul\Support\Models\Company;
use XoloCodigo\PetFood\Traceability\Filament\Pages\LotTraceability;
use XoloCodigo\PetFood\Traceability\Models\LotGenealogy;

require_once __DIR__.'/../../Support/PetFoodScenario.php';

beforeEach(function () {
    PetFoodScenario::bootstrap();
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $company = Company::query()->first() ?? Company::factory()->create();
    $user = User::factory()->create(['default_company_id' => $company->id]);
    $user->allowedCompanies()->syncWithoutDetaching([$company->id]);
    $this->actingAs($user);
    $this->company = $company;
});

/**
 * Núcleo (MP, NV-T-01) -> Premezcla (PMX-T-01) -> Croqueta (PT-T-01) -> Cliente Prueba x30.
 *
 * @return array<string, Lot>
 */
function tracedChain(Company $company): array
{
    $product = function (string $name, string $type): Product {
        $product = Product::factory()->create(['name' => $name]);
        $product->forceFill(['mx_item_type' => $type])->save();

        return $product;
    };

    $mp = $product('Núcleo prueba', 'raw_material');
    $pmx = $product('Premezcla prueba', 'wip');
    $pt = $product('Croqueta prueba', 'finished_good');

    $mpLot = Lot::factory()->create(['name' => 'NV-T-01', 'product_id' => $mp->id]);
    $mpLot->forceFill([
        'mx_supplier_id'  => Partner::factory()->create(['name' => 'Proveedor Prueba'])->id,
        'mx_supplier_lot' => 'PROV-77',
    ])->save();
    $pmxLot = Lot::factory()->create(['name' => 'PMX-T-01', 'product_id' => $pmx->id]);
    $ptLot = Lot::factory()->create(['name' => 'PT-T-01', 'product_id' => $pt->id]);
    Lot::factory()->create(['name' => 'SIN-T-01', 'product_id' => $mp->id]);

    LotGenealogy::factory()->create([
        'produced_lot_id' => $pmxLot->id, 'produced_product_id' => $pmx->id,
        'consumed_lot_id' => $mpLot->id, 'consumed_product_id' => $mp->id, 'quantity' => 8,
    ]);
    LotGenealogy::factory()->create([
        'produced_lot_id' => $ptLot->id, 'produced_product_id' => $pt->id,
        'consumed_lot_id' => $pmxLot->id, 'consumed_product_id' => $pmx->id, 'quantity' => 20,
    ]);

    PetFoodScenario::customerDelivery(Partner::factory()->create(['name' => 'Cliente Prueba']), $pt, $ptLot, 30);

    foreach (['inventories_lots', 'inventories_moves', 'inventories_move_lines', 'inventories_locations', 'products_products', 'partners_partners'] as $table) {
        DB::table($table)->update(['company_id' => $company->id]);
    }

    return ['mp' => $mpLot, 'pmx' => $pmxLot, 'pt' => $ptLot];
}

it('labels lots with their product and lists traced lots first', function () {
    tracedChain($this->company);

    $options = Livewire::test(LotTraceability::class)->instance()->lotOptions();

    expect(array_keys($options))->toBe(['Con trazabilidad', 'Sin uso todavía'])
        ->and($options['Con trazabilidad'])->toContain('NV-T-01 · Núcleo prueba')
        ->and($options['Sin uso todavía'])->toContain('SIN-T-01 · Núcleo prueba');
});

it('searches lots by lot or product name', function () {
    tracedChain($this->company);

    $results = Livewire::test(LotTraceability::class)->instance()->lotOptions('Croqueta');

    expect($results)->toContain('PT-T-01 · Croqueta prueba')
        ->and($results)->not->toContain('NV-T-01 · Núcleo prueba');
});

it('shows where a raw lot was used down to the customer, in one sentence and as a tree', function () {
    $lots = tracedChain($this->company);

    // html(), not assertSee(): after set() Livewire asserts on the JSON payload, where accents are \u-escaped.
    $html = Livewire::test(LotTraceability::class)->set('data.lot_id', $lots['mp']->id)->html();

    expect($html)->toContain('Este lote se usó en 2 lotes (1 de premezcla y 1 de producto terminado) y llegó a 1 cliente.')
        ->toMatch('/¿En qué se usó\?.*PMX-T-01.*↳.*PT-T-01.*Clientes afectados.*Cliente Prueba/su');
});

it('shows the lot card with type, supplier and supplier lot', function () {
    $lots = tracedChain($this->company);

    Livewire::test(LotTraceability::class)
        ->set('data.lot_id', $lots['mp']->id)
        ->assertSee('Materia prima')
        ->assertSee('Proveedor Prueba')
        ->assertSee('PROV-77');
});

it('shows what a finished lot is made of', function () {
    $lots = tracedChain($this->company);

    $html = Livewire::test(LotTraceability::class)->set('data.lot_id', $lots['pt']->id)->html();

    expect($html)->toMatch('/¿De qué está hecho\?.*PMX-T-01.*↳.*NV-T-01/su');
});

it('summarises a delivered finished lot by its customers', function () {
    $lots = tracedChain($this->company);

    $html = Livewire::test(LotTraceability::class)->set('data.lot_id', $lots['pt']->id)->html();

    expect($html)->toContain('Este lote llegó directo a 1 cliente.');
});

it('downloads the recall report as CSV', function () {
    $lots = tracedChain($this->company);

    Livewire::test(LotTraceability::class)
        ->set('data.lot_id', $lots['mp']->id)
        ->callAction('exportRecall')
        ->assertFileDownloaded('recall-NV-T-01.csv');
});
