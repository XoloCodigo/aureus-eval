<?php

namespace XoloCodigo\PetFood\Traceability\Observers;

use Illuminate\Support\Facades\Log;
use Webkul\Inventory\Enums\MoveState;
use Webkul\Manufacturing\Enums\ManufacturingOrderState;
use Webkul\Manufacturing\Models\Order;
use XoloCodigo\PetFood\Traceability\Models\LotGenealogy;

/**
 * Captures lot genealogy when a manufacturing order is completed.
 *
 * Registered from PetFoodServiceProvider via Order::observe(...), so the core
 * manufacturing module is never edited. When an order transitions to DONE, it
 * records one row per (produced lot × consumed raw-material lot), enabling
 * directed recalls (XoloCodigo\PetFood\Traceability\Services\LotTraceabilityService).
 */
class ManufacturingOrderObserver
{
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('state')) {
            return;
        }

        if ($order->state !== ManufacturingOrderState::DONE) {
            return;
        }

        $this->captureGenealogy($order);
    }

    protected function captureGenealogy(Order $order): void
    {
        $producedLot = $order->producingLot;

        if ($producedLot === null) {
            Log::info("petfood: manufacturing order {$order->id} marked done without a finished-good lot; genealogy skipped.");

            return;
        }

        // Idempotent: clear any prior rows for this order before re-inserting.
        LotGenealogy::where('manufacturing_order_id', $order->id)->delete();

        $order->loadMissing('rawMaterialMoves.lines');

        foreach ($order->rawMaterialMoves as $move) {
            foreach ($move->lines as $line) {
                if ($line->state !== MoveState::DONE) {
                    continue;
                }

                if ($line->lot_id === null) {
                    Log::info("petfood: raw-material move line {$line->id} has no lot; genealogy pair skipped.");

                    continue;
                }

                LotGenealogy::create([
                    'manufacturing_order_id' => $order->id,
                    'produced_lot_id'        => $producedLot->id,
                    'produced_product_id'    => $order->product_id,
                    'consumed_lot_id'        => $line->lot_id,
                    'consumed_product_id'    => $line->product_id,
                    'quantity'               => $line->qty,
                    'uom_id'                 => $line->uom_id,
                    'consumed_move_line_id'  => $line->id,
                    'company_id'             => $order->company_id,
                ]);
            }
        }
    }
}
