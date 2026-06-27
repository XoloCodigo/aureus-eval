<?php

use Illuminate\Support\Carbon;
use Webkul\Product\Models\Product;
use XoloCodigo\PetFood\Recipes\Services\BomVersionService;

require_once __DIR__.'/../../Support/PetFoodScenario.php';

beforeEach(fn () => PetFoodScenario::bootstrap());

it('captures the initial recipe as version 1', function () {
    $bom = PetFoodScenario::billOfMaterial();
    $a = Product::factory()->create();
    $b = Product::factory()->create();

    // Same request: both lines collapse into one version.
    PetFoodScenario::bomLine($bom, $a, 1);
    PetFoodScenario::bomLine($bom, $b, 2);

    $history = app(BomVersionService::class)->history($bom);

    expect($history)->toHaveCount(1)
        ->and($history->first()->version)->toBe(1)
        ->and($history->first()->recipe)->toHaveCount(2)
        ->and($history->first()->effective_to)->toBeNull();
});

it('opens a new version and closes the previous when the recipe changes', function () {
    $bom = PetFoodScenario::billOfMaterial();
    $line = PetFoodScenario::bomLine($bom, Product::factory()->create(), 1);

    PetFoodScenario::newRequest();
    $line->update(['quantity' => 5]);

    $service = app(BomVersionService::class);

    expect($service->history($bom))->toHaveCount(2)
        ->and($service->history($bom)->firstWhere('version', 1)->effective_to)->not->toBeNull()
        ->and($service->current($bom)->version)->toBe(2)
        ->and((float) $service->current($bom)->recipe[0]['quantity'])->toBe(5.0);
});

it('groups all line changes of one request into a single version', function () {
    $bom = PetFoodScenario::billOfMaterial();
    $line = PetFoodScenario::bomLine($bom, Product::factory()->create(), 1); // v1

    PetFoodScenario::newRequest();
    // One request: change A + add B + add C -> a single new version (v2).
    $line->update(['quantity' => 2]);
    PetFoodScenario::bomLine($bom, Product::factory()->create(), 3);
    PetFoodScenario::bomLine($bom, Product::factory()->create(), 4);

    $service = app(BomVersionService::class);

    expect($service->history($bom))->toHaveCount(2)
        ->and($service->current($bom)->version)->toBe(2)
        ->and($service->current($bom)->recipe)->toHaveCount(3);
});

it('does not create a version when the recipe is unchanged', function () {
    $bom = PetFoodScenario::billOfMaterial();
    $line = PetFoodScenario::bomLine($bom, Product::factory()->create(), 1);

    PetFoodScenario::newRequest();
    $line->update(['sort' => 9]); // non-recipe field

    expect(app(BomVersionService::class)->history($bom))->toHaveCount(1);
});

it('versionAt returns the version in effect at a given moment', function () {
    $t1 = Carbon::parse('2026-01-01 10:00:00');
    Carbon::setTestNow($t1);

    $bom = PetFoodScenario::billOfMaterial();
    $line = PetFoodScenario::bomLine($bom, Product::factory()->create(), 1); // v1 from t1

    PetFoodScenario::newRequest();
    $t2 = $t1->copy()->addDay();
    Carbon::setTestNow($t2);
    $line->update(['quantity' => 5]); // v2 from t2; v1 closed at t2

    $service = app(BomVersionService::class);

    expect($service->versionAt($bom, $t1->copy()->addHour())->version)->toBe(1)
        ->and($service->versionAt($bom, $t2->copy()->addHour())->version)->toBe(2);

    Carbon::setTestNow();
});
