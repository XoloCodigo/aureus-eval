<?php

use Illuminate\Support\Facades\DB;
use Webkul\Inventory\Enums\MoveState;
use Webkul\Inventory\Models\Lot;
use Webkul\Manufacturing\Enums\ManufacturingOrderState;
use Webkul\Partner\Models\Partner;
use Webkul\Product\Models\Product;
use Webkul\Security\Models\User;
use Webkul\Support\Models\Company;
use XoloCodigo\PetFood\Traceability\Services\LotTraceabilityService;

require_once __DIR__.'/../Support/PetFoodScenario.php';

beforeEach(fn () => PetFoodScenario::bootstrap());

/*
 * Guards for the company-tenancy contract of the core models our traceability
 * code reads (Lot, Move, MoveLine, Location, Order).
 *
 * Upstream is putting those models behind a company global scope that only
 * engages for an AUTHENTICATED user — so it is inert in console and in tests
 * that never sign anyone in. Our other tests never sign in, which means they
 * would stay green while a real browser session silently returns a truncated
 * recall: the exact shape of the 2026-07-22 icon regression, one layer down.
 *
 * These tests sign a user in and pin the chain to one company (the Winners
 * deployment is a single plant), so the recall runs through the same path a
 * real session takes. See [[project-upstream-sync]].
 */

/** The plant's company, with a signed-in user who belongs to it. */
function signInAtPlant(): Company
{
    $company = Company::query()->first() ?? Company::factory()->create();

    $user = User::factory()->create(['default_company_id' => $company->id]);
    $user->allowedCompanies()->syncWithoutDetaching([$company->id]);

    test()->actingAs($user);

    return $company;
}

/**
 * Put every inventory row the recall walks into one company — what a
 * single-plant deployment looks like once the scope starts filtering.
 */
function pinInventoryTo(Company $company): void
{
    foreach (['inventories_lots', 'inventories_moves', 'inventories_move_lines', 'inventories_locations'] as $table) {
        DB::table($table)->update(['company_id' => $company->id]);
    }
}

it('traces a two-level recall end to end for a signed-in user of the plant', function () {
    // Two levels on purpose. traceBackward() pushes each genealogy row before
    // hydrating $row->producedLot, so a single-level chain still resolves even
    // if the Lot relation comes back empty. Only the recursion into the second
    // level actually reads a scoped Lot — that is the step that goes silent.
    $mpProduct = Product::factory()->create();
    $wipProduct = Product::factory()->create();
    $ptProduct = Product::factory()->create();
    $mpLot = Lot::factory()->create(['product_id' => $mpProduct->id]);
    $wipLot = Lot::factory()->create(['product_id' => $wipProduct->id]);
    $ptLot = Lot::factory()->create(['product_id' => $ptProduct->id]);

    PetFoodScenario::rawMaterialOrder($wipLot, $mpLot, $mpProduct, 10)
        ->update(['state' => ManufacturingOrderState::DONE]);
    PetFoodScenario::rawMaterialOrder($ptLot, $wipLot, $wipProduct, 8)
        ->update(['state' => ManufacturingOrderState::DONE]);

    $customer = Partner::factory()->create();
    PetFoodScenario::customerDelivery($customer, $ptProduct, $ptLot, 4);

    $company = signInAtPlant();
    pinInventoryTo($company);

    $service = app(LotTraceabilityService::class);

    expect($service->traceBackward($mpLot)->pluck('lot_id')->all())
        ->toContain($wipLot->id, $ptLot->id);

    $affected = $service->affectedCustomers($mpLot);

    expect($affected)->toHaveCount(1)
        ->and($affected->first()['customer_id'])->toBe($customer->id)
        ->and($affected->first()['quantity'])->toBe(4.0);
});

it('stamps the supplier onto a received lot for a signed-in user of the plant', function () {
    // ReceiptLotOriginObserver stamps the supplier with a mass update
    // (Lot::query()->whereKey()->update()), and a global scope DOES filter mass
    // updates — a lot outside the session's company is skipped with no error and
    // no log, leaving the SADER supplier-origin chain silently broken.
    $supplier = Partner::factory()->create();
    $product = Product::factory()->create();
    $lot = Lot::factory()->create(['product_id' => $product->id]);

    $move = PetFoodScenario::supplierReceipt($supplier, $product, $lot);

    $company = signInAtPlant();
    pinInventoryTo($company);

    $move->update(['state' => MoveState::DONE]);

    expect($lot->fresh()->mx_supplier_id)->toBe($supplier->id);
});
