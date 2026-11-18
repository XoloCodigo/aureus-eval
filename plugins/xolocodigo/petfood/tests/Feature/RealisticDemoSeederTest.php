<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Webkul\Inventory\Enums\OperationType;
use Webkul\Inventory\Models\Lot;
use Webkul\Inventory\Models\ProductQuantity;
use Webkul\Manufacturing\Models\BillOfMaterial;
use Webkul\Partner\Models\Partner;
use Webkul\Product\Models\Product;
use XoloCodigo\PetFood\Database\Seeders\PetFoodRealisticDemoSeeder;
use XoloCodigo\PetFood\Recipes\Services\BomVersionService;

require_once __DIR__.'/../Support/PetFoodScenario.php';

beforeEach(function () {
    PetFoodScenario::bootstrap();
    PetFoodScenario::warehouse();
});

function recipeOf(string $productName): BillOfMaterial
{
    return BillOfMaterial::query()
        ->whereHas('product', fn ($query) => $query->where('name', $productName))
        ->firstOrFail();
}

it('seeds complete recipes with the warehouse manufacturing operation type', function () {
    (new PetFoodRealisticDemoSeeder)->run();

    $expectedLines = [
        'Croqueta Perro Adulto Premium 20 kg' => 8,
        'Croqueta Perro Cachorro 20 kg'       => 8,
        'Croqueta Gato Adulto 10 kg'          => 10,
        'Premezcla vitamínica-mineral'        => 6,
    ];

    foreach ($expectedLines as $productName => $lines) {
        $bom = recipeOf($productName);

        expect($bom->lines()->count())->toBe($lines)
            ->and($bom->operationType->type)->toBe(OperationType::MANUFACTURE)
            ->and(app(BomVersionService::class)->history($bom))->toHaveCount(1);
    }
});

it('seeds raw-material lots with supplier, supplier lot and expiration', function () {
    (new PetFoodRealisticDemoSeeder)->run();

    $lots = Lot::query()
        ->whereIn('product_id', Product::query()->where('mx_item_type', 'raw_material')->pluck('id'))
        ->get();

    expect($lots)->not->toBeEmpty();

    $lots->each(fn (Lot $lot) => expect($lot->mx_supplier_id)->not->toBeNull()
        ->and($lot->mx_supplier_lot)->not->toBeNull()
        ->and($lot->expiration_date)->not->toBeNull());
});

it('keeps the quarantined corn lot out of available stock', function () {
    (new PetFoodRealisticDemoSeeder)->run();

    $corn = Product::query()->where('name', 'Maíz amarillo')->firstOrFail();

    $available = Lot::query()->where('product_id', $corn->id)->get()
        ->map(fn (Lot $lot): float => (float) $lot->total_quantity)
        ->sort()->values()->all();

    expect($available)->toBe([0.0, 18000.0, 25000.0]);
});

it('ranks suppliers and customers so the vendor and customer lists show them', function () {
    // supplier_rank/customer_rank come from the accounts plugin (installed on the server, not in this test DB).
    if (! Schema::hasColumn('partners_partners', 'supplier_rank')) {
        Schema::table('partners_partners', function (Blueprint $table) {
            $table->integer('supplier_rank')->default(0);
            $table->integer('customer_rank')->default(0);
        });
    }

    (new PetFoodRealisticDemoSeeder)->run();

    expect(Partner::query()->where('name', 'Proteínas del Bajío')->value('supplier_rank'))->toBe(1)
        ->and(Partner::query()->where('name', 'Pet Shop La Huella')->value('customer_rank'))->toBe(1);
});

it('costs finished goods per bag from their recipe', function () {
    (new PetFoodRealisticDemoSeeder)->run();

    expect((float) Product::query()->where('name', 'Croqueta Perro Adulto Premium 20 kg')->value('cost'))->toBe(220.46)
        ->and((float) Product::query()->where('name', 'Premezcla vitamínica-mineral')->value('cost'))->toBe(28.0);
});

it('is idempotent', function () {
    (new PetFoodRealisticDemoSeeder)->run();
    $quantities = ProductQuantity::query()->count();

    (new PetFoodRealisticDemoSeeder)->run();

    expect(Product::query()->where('name', 'Maíz amarillo')->count())->toBe(1)
        ->and(ProductQuantity::query()->count())->toBe($quantities);
});
