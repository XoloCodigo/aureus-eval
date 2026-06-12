<?php

namespace XoloCodigo\PetFood\Traceability\Services;

use Illuminate\Support\Collection;
use Webkul\Inventory\Models\Lot;
use XoloCodigo\PetFood\Traceability\Models\LotGenealogy;

class LotTraceabilityService
{
    /**
     * Recall dirigido: dado un lote consumido (MP/WIP), devuelve todos los
     * lotes producidos aguas arriba (PT/WIP), recursivo y anti-ciclos.
     *
     * @return Collection<int, array{lot_id:int, product_id:int, manufacturing_order_id:int, quantity:float, depth:int}>
     */
    public function traceBackward(Lot $consumedLot, array &$visited = [], int $depth = 1): Collection
    {
        $results = collect();

        if (in_array($consumedLot->id, $visited, true)) {
            return $results;
        }

        $visited[] = $consumedLot->id;

        LotGenealogy::query()
            ->where('consumed_lot_id', $consumedLot->id)
            ->get()
            ->each(function (LotGenealogy $row) use (&$results, &$visited, $depth) {
                $results->push([
                    'lot_id'                 => $row->produced_lot_id,
                    'product_id'             => $row->produced_product_id,
                    'manufacturing_order_id' => $row->manufacturing_order_id,
                    'quantity'               => (float) $row->quantity,
                    'depth'                  => $depth,
                ]);

                if ($producedLot = $row->producedLot) {
                    $results = $results->merge(
                        $this->traceBackward($producedLot, $visited, $depth + 1)
                    );
                }
            });

        return $results;
    }

    /**
     * Trazabilidad inversa: dado un lote producido (PT/WIP), devuelve todos los
     * lotes de materia prima que lo formaron, recursivo y anti-ciclos.
     *
     * @return Collection<int, array{lot_id:int, product_id:int, manufacturing_order_id:int, quantity:float, depth:int}>
     */
    public function traceForward(Lot $producedLot, array &$visited = [], int $depth = 1): Collection
    {
        $results = collect();

        if (in_array($producedLot->id, $visited, true)) {
            return $results;
        }

        $visited[] = $producedLot->id;

        LotGenealogy::query()
            ->where('produced_lot_id', $producedLot->id)
            ->get()
            ->each(function (LotGenealogy $row) use (&$results, &$visited, $depth) {
                $results->push([
                    'lot_id'                 => $row->consumed_lot_id,
                    'product_id'             => $row->consumed_product_id,
                    'manufacturing_order_id' => $row->manufacturing_order_id,
                    'quantity'               => (float) $row->quantity,
                    'depth'                  => $depth,
                ]);

                if ($consumedLot = $row->consumedLot) {
                    $results = $results->merge(
                        $this->traceForward($consumedLot, $visited, $depth + 1)
                    );
                }
            });

        return $results;
    }
}
