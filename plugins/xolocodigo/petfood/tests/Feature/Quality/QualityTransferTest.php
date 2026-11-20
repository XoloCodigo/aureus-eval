<?php

use Webkul\Inventory\Enums\MoveState;
use Webkul\Inventory\Models\Lot;
use Webkul\Inventory\Models\ProductQuantity;
use Webkul\Product\Models\Product;
use Webkul\Security\Models\User;
use XoloCodigo\PetFood\Quality\Enums\MxQualityRole;
use XoloCodigo\PetFood\Quality\Services\QualityLocationService;

require_once __DIR__.'/../../Support/PetFoodScenario.php';

beforeEach(function () {
    PetFoodScenario::bootstrap();

    // Warehouse first: its creation hook looks up the global locations, which the company scope hides once logged in.
    $this->warehouse = PetFoodScenario::warehouse();
    $user = User::factory()->create(['default_company_id' => $this->warehouse->company_id]);
    $user->allowedCompanies()->syncWithoutDetaching([$this->warehouse->company_id]);
    $this->actingAs($user);

    $this->service = app(QualityLocationService::class);
    $this->quarantine = $this->service->locationFor($this->warehouse, MxQualityRole::Quarantine);

    $product = Product::factory()->create();
    $product->forceFill(['is_storable' => true, 'tracking' => 'lot'])->save();
    $this->lot = Lot::factory()->create(['product_id' => $product->id]);
    $this->stock = PetFoodScenario::stock($product, $this->warehouse->lotStockLocation, $this->lot, 100);
});

function heldIn($location, Lot $lot): ?ProductQuantity
{
    return ProductQuantity::query()->where('location_id', $location->id)->where('lot_id', $lot->id)->first();
}

it('retains part of a lot through a done stock move that keeps the reason and the user', function () {
    $move = $this->service->transfer($this->stock, $this->quarantine, 30, 'Humedad alta');

    expect($move->state)->toBe(MoveState::DONE)
        ->and($move->origin)->toBe('Humedad alta')
        ->and($move->creator_id)->toBe(auth()->id())
        ->and((float) $this->lot->refresh()->total_quantity)->toBe(70.0)
        ->and((float) heldIn($this->quarantine, $this->lot)->quantity)->toBe(30.0);
});

it('releases quarantined material back to available stock', function () {
    $this->service->transfer($this->stock, $this->quarantine, 30, 'Muestreo');

    $this->service->transfer(heldIn($this->quarantine, $this->lot), $this->warehouse->lotStockLocation, 30, 'Análisis aprobado');

    expect((float) $this->lot->refresh()->total_quantity)->toBe(100.0)
        ->and((float) heldIn($this->quarantine, $this->lot)?->quantity)->toBe(0.0);
});

it('refuses to move more than the unreserved quantity', function () {
    $this->stock->update(['reserved_quantity' => 40]);

    expect(fn () => $this->service->transfer($this->stock, $this->quarantine, 70, 'Sospecha'))
        ->toThrow(InvalidArgumentException::class);

    expect((float) $this->stock->refresh()->quantity)->toBe(100.0);
});

it('checks the current stock, not the stale copy a page opened earlier', function () {
    $openedEarlier = ProductQuantity::query()->find($this->stock->id);
    $this->service->transfer($this->stock, $this->quarantine, 80, 'Primer usuario');

    expect(fn () => $this->service->transfer($openedEarlier, $this->quarantine, 50, 'Segundo usuario'))
        ->toThrow(InvalidArgumentException::class);

    expect((float) $this->stock->refresh()->quantity)->toBe(20.0);
});
