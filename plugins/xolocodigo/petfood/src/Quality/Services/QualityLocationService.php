<?php

namespace XoloCodigo\PetFood\Quality\Services;

use Illuminate\Support\Collection;
use Webkul\Inventory\Enums\LocationType;
use Webkul\Inventory\Models\Location;
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
            ->map(fn (MxQualityRole $role) => $this->ensureLocation($warehouse, $role))
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

    protected function ensureLocation(Warehouse $warehouse, MxQualityRole $role): Location
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
}
