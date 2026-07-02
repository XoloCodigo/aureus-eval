<?php

namespace XoloCodigo\PetFood\Traceability\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Webkul\Inventory\Models\Lot;
use Webkul\Product\Models\Product;
use XoloCodigo\PetFood\Traceability\Services\LotTraceabilityService;

/**
 * Read-only lot genealogy explorer.
 *
 * Pick a lot and see, in both directions, its genealogy:
 *  - backward: the finished-good lots affected by it (directed recall);
 *  - forward: the raw-material lots that formed it.
 *
 * This page is presentation only: it consumes LotTraceabilityService and never
 * touches the petfood_lot_genealogies table directly, so it can be replaced or
 * extended per client without affecting the engine.
 */
class LotTraceability extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Calidad e Inocuidad';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Genealogía de lotes';

    protected static ?string $title = 'Genealogía de lotes';

    protected string $view = 'petfood::filament.pages.lot-traceability';

    public ?int $lotId = null;

    /** @var array<int, array{lot_id:int, product_id:int, manufacturing_order_id:int, quantity:float, depth:int}> */
    public array $backward = [];

    /** @var array<int, array{lot_id:int, product_id:int, manufacturing_order_id:int, quantity:float, depth:int}> */
    public array $forward = [];

    /** @var array<int, array{customer_id:?int, customer:?string, lot_id:int, lot:?string, product:?string, quantity:float, shipped_at:mixed}> */
    public array $affectedCustomers = [];

    /**
     * @return array<int, string>
     */
    public function getLotOptionsProperty(): array
    {
        return Lot::query()
            ->orderByDesc('id')
            ->limit(200)
            ->pluck('name', 'id')
            ->all();
    }

    public function updatedLotId(): void
    {
        if (! $this->lotId) {
            $this->backward = [];
            $this->forward = [];
            $this->affectedCustomers = [];

            return;
        }

        $lot = Lot::find($this->lotId);

        if (! $lot) {
            return;
        }

        $service = app(LotTraceabilityService::class);

        $this->backward = $this->withNames($service->traceBackward($lot)->all());
        $this->forward = $this->withNames($service->traceForward($lot)->all());
        $this->affectedCustomers = $service->affectedCustomers($lot)->all();
    }

    /**
     * Enrich genealogy nodes with readable lot and product names for display.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function withNames(array $nodes): array
    {
        if ($nodes === []) {
            return $nodes;
        }

        $lots = Lot::query()->whereIn('id', array_column($nodes, 'lot_id'))->pluck('name', 'id');
        $products = Product::query()->whereIn('id', array_column($nodes, 'product_id'))->pluck('name', 'id');

        return array_map(fn (array $node): array => $node + [
            'lot_name'     => $lots[$node['lot_id']] ?? ('#'.$node['lot_id']),
            'product_name' => $products[$node['product_id']] ?? ('#'.$node['product_id']),
        ], $nodes);
    }
}
