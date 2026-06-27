<?php

namespace XoloCodigo\PetFood\Traceability\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Webkul\Inventory\Models\Lot;
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

        $this->backward = $service->traceBackward($lot)->all();
        $this->forward = $service->traceForward($lot)->all();
        $this->affectedCustomers = $service->affectedCustomers($lot)->all();
    }
}
