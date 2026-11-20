<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Webkul\Inventory\Models\Lot;
use Webkul\Inventory\Models\ProductQuantity;
use Webkul\Product\Models\Product;
use Webkul\Security\Models\User;
use Webkul\Support\Models\Company;
use XoloCodigo\PetFood\Quality\Enums\MxQualityRole;
use XoloCodigo\PetFood\Quality\Filament\Pages\RetainedLots;
use XoloCodigo\PetFood\Quality\Services\QualityLocationService;

require_once __DIR__.'/../../Support/PetFoodScenario.php';

beforeEach(function () {
    PetFoodScenario::bootstrap();
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    // Warehouse first: its creation hook looks up the global locations, which the company scope hides once logged in.
    $this->warehouse = PetFoodScenario::warehouse();
    $this->user = User::factory()->create(['name' => 'Ana Calidad', 'default_company_id' => $this->warehouse->company_id]);
    $this->user->allowedCompanies()->syncWithoutDetaching([$this->warehouse->company_id]);
    $this->actingAs($this->user);

    $this->service = app(QualityLocationService::class);
    $this->quarantine = $this->service->locationFor($this->warehouse, MxQualityRole::Quarantine);

    $product = Product::factory()->create(['name' => 'Maiz prueba']);
    $product->forceFill(['is_storable' => true, 'tracking' => 'lot'])->save();
    $this->lot = Lot::factory()->create(['name' => 'MZ-Q-01', 'product_id' => $product->id]);
    $this->stock = PetFoodScenario::stock($product, $this->warehouse->lotStockLocation, $this->lot, 100);
});

function retainedIn($location, Lot $lot): ?ProductQuantity
{
    return ProductQuantity::query()->where('location_id', $location->id)->where('lot_id', $lot->id)->first();
}

it('lists retained lots with their area, reason and responsible', function () {
    $this->service->transfer($this->stock, $this->quarantine, 30, 'Humedad alta');

    Livewire::test(RetainedLots::class)
        ->assertCanSeeTableRecords([retainedIn($this->quarantine, $this->lot)])
        ->assertCanNotSeeTableRecords([$this->stock])
        ->assertSee(['MZ-Q-01', 'Maiz prueba', 'Cuarentena', 'Humedad alta', 'Ana Calidad']);
});

it('releases a retained lot from its row', function () {
    $this->service->transfer($this->stock, $this->quarantine, 30, 'Muestreo');

    Livewire::test(RetainedLots::class)
        ->callAction(TestAction::make('release')->table(retainedIn($this->quarantine, $this->lot)), data: ['quantity' => 30, 'reason' => 'Laboratorio aprobado'])
        ->assertHasNoFormErrors();

    expect((float) $this->lot->refresh()->total_quantity)->toBe(100.0);
});

it('sends part of a quarantined lot to rejection', function () {
    $this->service->transfer($this->stock, $this->quarantine, 30, 'Muestreo');

    Livewire::test(RetainedLots::class)
        ->callAction(TestAction::make('reject')->table(retainedIn($this->quarantine, $this->lot)), data: ['quantity' => 10, 'reason' => 'Aflatoxinas fuera de rango']);

    $rejected = $this->service->locationFor($this->warehouse, MxQualityRole::Rejected);

    expect((float) retainedIn($rejected, $this->lot)->quantity)->toBe(10.0)
        ->and((float) retainedIn($this->quarantine, $this->lot)->quantity)->toBe(20.0);
});

it('retains a lot from the header action', function () {
    Livewire::test(RetainedLots::class)
        ->callAction('retain', data: ['stock_id' => $this->stock->id, 'area' => 'quarantine', 'quantity' => 25, 'reason' => 'Olor extraño'])
        ->assertHasNoFormErrors();

    expect((float) $this->lot->refresh()->total_quantity)->toBe(75.0)
        ->and((float) retainedIn($this->quarantine, $this->lot)->quantity)->toBe(25.0);
});

it('does not let the form retain more than the available quantity', function () {
    Livewire::test(RetainedLots::class)
        ->callAction('retain', data: ['stock_id' => $this->stock->id, 'area' => 'quarantine', 'quantity' => 500, 'reason' => 'Sospecha'])
        ->assertHasFormErrors(['quantity']);

    expect((float) $this->lot->refresh()->total_quantity)->toBe(100.0);
});

it('does not retain stock of a company the user cannot access', function () {
    $other = Company::factory()->create();
    $foreign = PetFoodScenario::stock($this->stock->product, $this->warehouse->lotStockLocation, Lot::factory()->create(['product_id' => $this->stock->product_id]), 50);
    $foreign->forceFill(['company_id' => $other->id])->saveQuietly();

    Livewire::test(RetainedLots::class)
        ->callAction('retain', data: ['stock_id' => $foreign->id, 'area' => 'quarantine', 'quantity' => 10, 'reason' => 'Intento ajeno'])
        ->assertHasFormErrors(['stock_id']);

    expect((float) $foreign->refresh()->quantity)->toBe(50.0);
});
