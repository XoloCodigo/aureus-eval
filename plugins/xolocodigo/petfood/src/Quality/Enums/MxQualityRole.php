<?php

namespace XoloCodigo\PetFood\Quality\Enums;

/**
 * Role of a quality inventory location in the petfood flow.
 *
 *  - Quarantine — material pending QC; retained, can later be released.
 *  - Rejected   — failed QC; held for disposal with an authorized vendor.
 *  - Waste      — scrap/merma; discarded.
 *
 * All three are created on locations with is_scrap = true (so the core keeps
 * the material out of reservation and available stock); this role only
 * distinguishes them for the petfood flow, reports and UI.
 */
enum MxQualityRole: string
{
    case Quarantine = 'quarantine';
    case Rejected = 'rejected';
    case Waste = 'waste';

    public function label(): string
    {
        return match ($this) {
            self::Quarantine => 'Cuarentena',
            self::Rejected   => 'Rechazo',
            self::Waste      => 'Merma',
        };
    }
}
