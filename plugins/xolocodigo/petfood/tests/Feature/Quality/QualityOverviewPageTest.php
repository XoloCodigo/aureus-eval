<?php

use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Webkul\Inventory\Models\Lot;
use Webkul\Manufacturing\Enums\ManufacturingOrderState;
use Webkul\Manufacturing\Models\Order;
use Webkul\Product\Models\Product;
use Webkul\Security\Models\User;
use Webkul\Support\Models\Company;
use XoloCodigo\PetFood\Quality\Enums\MxQualityRole;
use XoloCodigo\PetFood\Quality\Filament\Pages\QualityOverview;
use XoloCodigo\PetFood\Quality\Filament\Widgets\QualityStats;
use XoloCodigo\PetFood\Quality\Services\QualityLocationService;

require_once __DIR__.'/../../Support/PetFoodScenario.php';

beforeEach(function () {
    PetFoodScenario::bootstrap();
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    // Warehouse first: its creation hook looks up the global locations, which the company scope hides once logged in.
    $this->warehouse = PetFoodScenario::warehouse();
    $this->user = User::factory()->create(['default_company_id' => $this->warehouse->company_id]);
    $this->user->allowedCompanies()->syncWithoutDetaching([$this->warehouse->company_id]);
    $this->actingAs($this->user);

    $this->product = Product::factory()->create(['name' => 'Harina de pescado']);
    $this->product->forceFill(['is_storable' => true, 'tracking' => 'lot'])->save();
});

function lotExpiringIn(int $days, float $stock, string $name): Lot
{
    $lot = Lot::factory()->create(['name' => $name, 'product_id' => test()->product->id, 'expiration_date' => now()->addDays($days)]);

    if ($stock > 0) {
        PetFoodScenario::stock(test()->product, test()->warehouse->lotStockLocation, $lot, $stock);
    }

    return $lot;
}

function finishedOrder(ManufacturingOrderState $state, $finishedAt): void
{
    Order::factory()->make(['product_id' => test()->product->id, 'state' => $state, 'finished_at' => $finishedAt])->saveQuietly();
}

/** @return Collection<string, Stat> the KPI cards keyed by their label */
function qualityStats(): Collection
{
    $widget = Livewire::test(QualityStats::class)->instance();

    return collect((fn (): array => $this->getStats())->call($widget))->keyBy(fn (Stat $stat): string => $stat->getLabel());
}

it('lists lots with stock that expire within 30 days, expired ones included', function () {
    $soon = lotExpiringIn(10, 40, 'HP-SOON');
    $expired = lotExpiringIn(-3, 15, 'HP-OLD');
    $later = lotExpiringIn(60, 40, 'HP-LATER');
    $empty = lotExpiringIn(5, 0, 'HP-EMPTY');

    Livewire::test(QualityOverview::class)
        ->assertSee(['Lotes retenidos', 'Por vencer en 30 días', 'Producción del mes'])
        ->assertCanSeeTableRecords([$soon, $expired])
        ->assertCanNotSeeTableRecords([$later, $empty]);

    expect(qualityStats()['Por vencer en 30 días']->getValue())->toBe(2)
        ->and(qualityStats()['Por vencer en 30 días']->getDescription())->toBe('1 ya vencido');
});

it('counts retained lots', function () {
    $stock = PetFoodScenario::stock($this->product, $this->warehouse->lotStockLocation, lotExpiringIn(90, 0, 'HP-Q'), 100);
    $quality = app(QualityLocationService::class);
    $quality->transfer($stock, $quality->locationFor($this->warehouse, MxQualityRole::Quarantine), 30, 'Humedad alta');

    expect(qualityStats()['Lotes retenidos']->getValue())->toBe(1);
});

it('counts the manufacturing orders finished this month', function () {
    finishedOrder(ManufacturingOrderState::DONE, now());
    finishedOrder(ManufacturingOrderState::DONE, now()->startOfMonth());
    finishedOrder(ManufacturingOrderState::DONE, now()->startOfMonth()->subSecond());
    finishedOrder(ManufacturingOrderState::DRAFT, now());

    expect(qualityStats()['Producción del mes']->getValue())->toBe(2);
});

it('does not show expiring lots of a company the user cannot access', function () {
    $foreign = lotExpiringIn(10, 40, 'HP-AJENO');
    $foreign->forceFill(['company_id' => Company::factory()->create()->id])->saveQuietly();

    Livewire::test(QualityOverview::class)->assertCanNotSeeTableRecords([$foreign]);

    expect(qualityStats()['Por vencer en 30 días']->getValue())->toBe(0);
});
