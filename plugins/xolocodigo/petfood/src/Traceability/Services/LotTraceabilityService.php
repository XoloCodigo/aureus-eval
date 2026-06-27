<?php

namespace XoloCodigo\PetFood\Traceability\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Webkul\Inventory\Enums\LocationType;
use Webkul\Inventory\Enums\MoveState;
use Webkul\Inventory\Models\Lot;
use Webkul\Inventory\Models\MoveLine;
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

    /**
     * Recall cerrado: dado un lote de materia prima reclamado, devuelve los
     * CLIENTES que recibieron lotes producidos (PT/WIP) que lo contienen.
     * Combina la genealogía (MP -> PT) con las entregas a cliente, que ya viven
     * en los moves del core (destino = ubicación CUSTOMER, partner = cliente).
     * Una fila por entrega afectada.
     *
     * @return Collection<int, array{customer_id:?int, customer:?string, lot_id:int, lot:?string, product:?string, quantity:float, shipped_at:?Carbon}>
     */
    public function affectedCustomers(Lot $rawLot): Collection
    {
        $lotIds = $this->traceBackward($rawLot)
            ->pluck('lot_id')
            ->push($rawLot->id)
            ->unique()
            ->all();

        return MoveLine::query()
            ->whereIn('lot_id', $lotIds)
            ->whereHas('move', function ($query) {
                $query->where('state', MoveState::DONE)
                    ->whereHas('destinationLocation', fn ($location) => $location->where('type', LocationType::CUSTOMER));
            })
            ->with(['move.partner', 'lot.product'])
            ->get()
            ->map(fn (MoveLine $line) => [
                'customer_id' => $line->move->partner_id,
                'customer'    => $line->move->partner?->name,
                'lot_id'      => $line->lot_id,
                'lot'         => $line->lot?->name,
                'product'     => $line->lot?->product?->name,
                'quantity'    => (float) $line->qty,
                'shipped_at'  => $line->move->scheduled_at,
            ])
            ->values();
    }
}
