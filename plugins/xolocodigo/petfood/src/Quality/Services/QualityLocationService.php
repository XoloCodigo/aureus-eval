<?php

namespace XoloCodigo\PetFood\Quality\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Webkul\Inventory\Enums\LocationType;
use Webkul\Inventory\Enums\MoveState;
use Webkul\Inventory\Facades\Inventory;
use Webkul\Inventory\Models\Location;
use Webkul\Inventory\Models\Move;
use Webkul\Inventory\Models\ProductQuantity;
use Webkul\Inventory\Models\Warehouse;
use XoloCodigo\PetFood\Quality\Enums\MxQualityRole;

/**
 * Creates and finds the quality locations (quarantine / rejected / waste).
 *
 * Quality locations are ordinary INTERNAL locations flagged is_scrap = true —
 * so the core keeps their stock out of reservation and out of a lot's available
 * quantity — plus an mx_quality_role that distinguishes their purpose. Material
 * is moved in/out with the regular inventory transfers; releasing it is simply
 * moving it back to a normal internal location.
 */
class QualityLocationService
{
    /**
     * Ensure the three quality locations exist for the warehouse, creating any
     * that are missing. Idempotent.
     *
     * @return Collection<int, Location>
     */
    public function ensureFor(Warehouse $warehouse): Collection
    {
        return collect(MxQualityRole::cases())
            ->map(fn (MxQualityRole $role) => $this->locationFor($warehouse, $role))
            ->values();
    }

    /**
     * Quality locations across all warehouses, optionally filtered by role.
     *
     * @return Collection<int, Location>
     */
    public function qualityLocations(?MxQualityRole $role = null): Collection
    {
        return Location::query()
            ->whereNotNull('mx_quality_role')
            ->when($role, fn ($query) => $query->where('mx_quality_role', $role->value))
            ->get();
    }

    /** The warehouse's location for a quality role, created when missing. */
    public function locationFor(Warehouse $warehouse, MxQualityRole $role): Location
    {
        $existing = Location::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('mx_quality_role', $role->value)
            ->first();

        if ($existing) {
            return $existing;
        }

        // The core Location::creating hook derives warehouse_id from the parent
        // (and nulls it when there is no parent), so we hang the quality location
        // under the warehouse's root view location rather than setting
        // warehouse_id directly.
        $location = new Location([
            'name'       => $role->label(),
            'type'       => LocationType::INTERNAL,
            'is_scrap'   => true,
            'parent_id'  => $warehouse->view_location_id,
            'company_id' => $warehouse->company_id,
        ]);

        // mx_quality_role is not in the core Location $fillable; set it directly.
        $location->mx_quality_role = $role->value;
        $location->save();

        return $location;
    }

    /**
     * Move part of a lot's stock between locations (retain, release, reject or waste) through the
     * core move flow, so the stock history keeps the date, the user and the reason (origin).
     * Only the unreserved quantity can move: reserved stock belongs to another transfer.
     */
    public function transfer(ProductQuantity $stock, Location $destination, float $quantity, string $reason): Move
    {
        return DB::transaction(function () use ($stock, $destination, $quantity, $reason): Move {
            // Re-read under lock: the caller's copy may be stale (someone moved this lot since the page opened).
            $stock = ProductQuantity::query()->lockForUpdate()->findOrFail($stock->id);
            $product = $stock->product;

            if ($quantity <= 0 || float_compare($quantity, $stock->available_quantity, precisionRounding: $product->uom->rounding) > 0) {
                throw new InvalidArgumentException('La cantidad debe ser mayor a cero y no superar lo disponible del lote en esa ubicación.');
            }

            $line = [
                'product_id'              => $product->id,
                'uom_id'                  => $product->uom_id,
                'source_location_id'      => $stock->location_id,
                'destination_location_id' => $destination->id,
                'company_id'              => $stock->location->company_id,
            ];

            // Created confirmed and picked, without an operation: the core confirm step would
            // reserve by removal strategy (any lot), and the warehouse's internal type is archived.
            $move = Move::create($line + [
                'name'            => $product->name,
                'reference'       => 'Calidad',
                'origin'          => $reason,
                'state'           => MoveState::CONFIRMED,
                'is_picked'       => true,
                'product_uom_qty' => $quantity,
            ]);

            // Pins the exact lot; the line's created hook reserves it as a hand-picked line would.
            $move->lines()->create($line + ['lot_id' => $stock->lot_id, 'qty' => $quantity]);

            return Inventory::completeMoves(Move::query()->whereKey($move->id)->get())->first()->refresh();
        });
    }
}
