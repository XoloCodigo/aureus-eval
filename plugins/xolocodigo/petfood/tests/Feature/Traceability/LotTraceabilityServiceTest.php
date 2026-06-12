<?php

use Webkul\Inventory\Models\Lot;
use XoloCodigo\PetFood\Traceability\Models\LotGenealogy;
use XoloCodigo\PetFood\Traceability\Services\LotTraceabilityService;

require_once __DIR__.'/../../../../../webkul/support/tests/Helpers/TestBootstrapHelper.php';

beforeEach(function () {
    TestBootstrapHelper::ensureERPInstalled();

    // Install plugin chain in dependency order so ALTER TABLE migrations
    // always run after the base table exists.
    if (! Schema::hasTable('inventories_lots')) {
        Artisan::call('inventories:install', ['--no-interaction' => true]);
    }

    if (! Schema::hasTable('manufacturing_orders')) {
        Artisan::call('manufacturing:install', ['--no-interaction' => true]);
    }

    if (! Schema::hasTable('petfood_lot_genealogies')) {
        Artisan::call('petfood:install', ['--no-interaction' => true]);
    }
});

it('traces backward from a raw-material lot to the finished-good lots it affected', function () {
    $service = app(LotTraceabilityService::class);

    $rawLot = Lot::factory()->create();
    $finishedLot = Lot::factory()->create();

    LotGenealogy::factory()->create([
        'consumed_lot_id' => $rawLot->id,
        'produced_lot_id' => $finishedLot->id,
    ]);

    $affected = $service->traceBackward($rawLot);

    expect($affected->pluck('lot_id'))->toContain($finishedLot->id);
});

it('traces backward through a multi-level chain (MP -> WIP -> PT)', function () {
    $service = app(LotTraceabilityService::class);

    $mpLot = Lot::factory()->create();
    $wipLot = Lot::factory()->create();
    $ptLot = Lot::factory()->create();

    LotGenealogy::factory()->create([
        'consumed_lot_id' => $mpLot->id,
        'produced_lot_id' => $wipLot->id,
    ]);
    LotGenealogy::factory()->create([
        'consumed_lot_id' => $wipLot->id,
        'produced_lot_id' => $ptLot->id,
    ]);

    $affected = $service->traceBackward($mpLot)->pluck('lot_id');

    expect($affected)->toContain($wipLot->id)
        ->and($affected)->toContain($ptLot->id);
});

it('traces forward from a finished-good lot to the raw-material lots that formed it', function () {
    $service = app(LotTraceabilityService::class);

    $rawLot = Lot::factory()->create();
    $finishedLot = Lot::factory()->create();

    LotGenealogy::factory()->create([
        'consumed_lot_id' => $rawLot->id,
        'produced_lot_id' => $finishedLot->id,
    ]);

    $sources = $service->traceForward($finishedLot)->pluck('lot_id');

    expect($sources)->toContain($rawLot->id);
});

it('does not loop infinitely on cyclic genealogy', function () {
    $service = app(LotTraceabilityService::class);

    $a = Lot::factory()->create();
    $b = Lot::factory()->create();

    LotGenealogy::factory()->create(['consumed_lot_id' => $a->id, 'produced_lot_id' => $b->id]);
    LotGenealogy::factory()->create(['consumed_lot_id' => $b->id, 'produced_lot_id' => $a->id]);

    $affected = $service->traceBackward($a)->pluck('lot_id');

    expect($affected)->toContain($b->id);
});
