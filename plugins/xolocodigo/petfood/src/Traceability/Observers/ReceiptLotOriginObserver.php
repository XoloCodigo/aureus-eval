<?php

namespace XoloCodigo\PetFood\Traceability\Observers;

use Webkul\Inventory\Enums\LocationType;
use Webkul\Inventory\Enums\MoveState;
use Webkul\Inventory\Models\Lot;
use Webkul\Inventory\Models\Move;

/**
 * Records the supplier of an internal lot when goods are received from a vendor.
 *
 * Registered from PetFoodServiceProvider via Move::observe(...), so the core
 * inventories module is never edited. When a stock move whose source is a
 * supplier location reaches DONE (a receipt), each received lot gets its
 * mx_supplier_id stamped from the move's partner — giving a direct, reliable
 * lot -> supplier link instead of a fragile query through move lines.
 *
 * The source-location type is used (rather than a purchase_order_line link) so
 * the rule lives entirely in inventories and does not require the purchases
 * module. The supplier's own lot number (mx_supplier_lot) is left for a later
 * increment, once the client confirms whether and where they capture it.
 */
class ReceiptLotOriginObserver
{
    public function updated(Move $move): void
    {
        if (! $move->wasChanged('state')) {
            return;
        }

        if ($move->state !== MoveState::DONE) {
            return;
        }

        if (! $this->isSupplierReceipt($move)) {
            return;
        }

        $this->captureOrigin($move);
    }

    protected function isSupplierReceipt(Move $move): bool
    {
        return $move->sourceLocation?->type === LocationType::SUPPLIER;
    }

    protected function captureOrigin(Move $move): void
    {
        $supplierId = $move->partner_id;

        if ($supplierId === null) {
            return;
        }

        $move->loadMissing('lines');

        foreach ($move->lines as $line) {
            if ($line->lot_id === null) {
                continue;
            }

            // Stamp the supplier directly on the lot. Do not overwrite an
            // existing supplier (a lot is received once, from one supplier).
            Lot::query()
                ->whereKey($line->lot_id)
                ->whereNull('mx_supplier_id')
                ->update(['mx_supplier_id' => $supplierId]);
        }
    }
}
