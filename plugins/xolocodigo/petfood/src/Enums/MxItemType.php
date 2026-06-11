<?php

namespace XoloCodigo\PetFood\Enums;

/**
 * Petfood industry item taxonomy.
 *
 * Orthogonal to Aureus's product `type` field (goods/service/consumable).
 * This enum distinguishes a product's role in the manufacturing flow,
 * which is what petfood regulatory traceability (SENASICA, NOM) cares
 * about: knowing whether a given batch is raw material, work-in-progress,
 * or finished good when tracing lot genealogy.
 *
 *  - RawMaterial    (Materia Prima)     — ingredient consumed in production
 *  - Wip            (Producto en Proceso) — intermediate output of a stage
 *  - FinishedGood   (Producto Terminado) — end product ready for sale
 *  - Consumable     (Consumible)         — supplies not tracked through MFG
 *                                          (lubricants, packaging when not
 *                                          lot-tracked, generic cleaning, etc.)
 */
enum MxItemType: string
{
    case RawMaterial = 'raw_material';
    case Wip = 'wip';
    case FinishedGood = 'finished_good';
    case Consumable = 'consumable';

    /**
     * Human-readable Spanish label (used in Filament selects + reports).
     */
    public function label(): string
    {
        return match ($this) {
            self::RawMaterial  => 'Materia prima',
            self::Wip          => 'Producto en proceso',
            self::FinishedGood => 'Producto terminado',
            self::Consumable   => 'Consumible',
        };
    }

    /**
     * Short abbreviation used in compact tables, labels, lot prefixes.
     */
    public function abbreviation(): string
    {
        return match ($this) {
            self::RawMaterial  => 'MP',
            self::Wip          => 'WIP',
            self::FinishedGood => 'PT',
            self::Consumable   => 'CONS',
        };
    }

    /**
     * Whether this item type is part of the manufacturing genealogy chain.
     * Consumables are not tracked through manufacturing, so they do not
     * appear in batch_genealogy traces.
     */
    public function isInManufacturingChain(): bool
    {
        return match ($this) {
            self::RawMaterial, self::Wip, self::FinishedGood => true,
            self::Consumable                                 => false,
        };
    }
}
