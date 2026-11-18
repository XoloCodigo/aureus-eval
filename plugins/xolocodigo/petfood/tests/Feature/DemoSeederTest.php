<?php

use Webkul\Inventory\Enums\OperationType;
use Webkul\Manufacturing\Models\BillOfMaterial;
use XoloCodigo\PetFood\Database\Seeders\PetFoodDemoSeeder;

require_once __DIR__.'/../Support/PetFoodScenario.php';

beforeEach(fn () => PetFoodScenario::bootstrap());

/**
 * The MO form copies the recipe's operation type and only accepts types of kind
 * "manufacture"; seeding it with "Receipts" made every new order fail validation
 * ("tipo de operación inválido") and pointed raw-material consumption at Vendors.
 */
it('seeds the demo recipe with the warehouse manufacturing operation type', function () {
    PetFoodScenario::warehouse();

    (new PetFoodDemoSeeder)->run();

    $bom = BillOfMaterial::query()->where('code', 'BOM-CROQ-001')->firstOrFail();

    expect($bom->operationType->type)->toBe(OperationType::MANUFACTURE);
});
