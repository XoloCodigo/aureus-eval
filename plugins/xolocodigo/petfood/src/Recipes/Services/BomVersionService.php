<?php

namespace XoloCodigo\PetFood\Recipes\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Webkul\Manufacturing\Models\BillOfMaterial;
use XoloCodigo\PetFood\Recipes\Models\BomVersion;

/**
 * Captures and queries immutable versions of a BOM's recipe.
 *
 * Registered as a singleton so that all the line-change events of a single
 * edit (which fire one observer call per line) collapse into ONE version:
 * the first call in a request opens the version, the rest update it in place.
 */
class BomVersionService
{
    /** @var array<int, int> bill_of_material_id => version id opened in this request */
    private array $openedThisRequest = [];

    /**
     * Snapshot the BOM's current recipe as a new version if it differs from the
     * one in effect. Returns the version touched, or null when nothing changed.
     */
    public function snapshot(BillOfMaterial $bom): ?BomVersion
    {
        $recipe = $this->recipeOf($bom);

        // Same request, same BOM: fold this change into the version we opened.
        if (isset($this->openedThisRequest[$bom->id])) {
            $version = BomVersion::find($this->openedThisRequest[$bom->id]);

            if ($version) {
                $version->update(['recipe' => $recipe, 'changed_by' => Auth::id()]);

                return $version;
            }
        }

        $current = $this->current($bom);

        if ($current && $this->sameRecipe($current->recipe, $recipe)) {
            return null;
        }

        if ($current) {
            $current->update(['effective_to' => now()]);
        }

        $version = BomVersion::create([
            'bill_of_material_id' => $bom->id,
            'version'             => ($current?->version ?? 0) + 1,
            'effective_from'      => now(),
            'effective_to'        => null,
            'recipe'              => $recipe,
            'changed_by'          => Auth::id(),
        ]);

        $this->openedThisRequest[$bom->id] = $version->id;

        return $version;
    }

    /** The version currently in effect (open window), if any. */
    public function current(BillOfMaterial $bom): ?BomVersion
    {
        return BomVersion::where('bill_of_material_id', $bom->id)
            ->whereNull('effective_to')
            ->orderByDesc('version')
            ->first();
    }

    /** The version in effect at a given moment. */
    public function versionAt(BillOfMaterial $bom, Carbon $moment): ?BomVersion
    {
        return BomVersion::where('bill_of_material_id', $bom->id)
            ->where('effective_from', '<=', $moment)
            ->where(function ($query) use ($moment) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $moment);
            })
            ->orderByDesc('version')
            ->first();
    }

    /** Full version history of a recipe, oldest first. */
    public function history(BillOfMaterial $bom): Collection
    {
        return BomVersion::where('bill_of_material_id', $bom->id)
            ->orderBy('version')
            ->get();
    }

    /** Forget per-request grouping state (call between tests). */
    public function resetRequestState(): void
    {
        $this->openedThisRequest = [];
    }

    /**
     * @return array<int, array{product_id:int, quantity:float, uom_id:int}>
     */
    private function recipeOf(BillOfMaterial $bom): array
    {
        return $bom->lines()
            ->get(['product_id', 'quantity', 'uom_id'])
            ->map(fn ($line) => [
                'product_id' => (int) $line->product_id,
                'quantity'   => (float) $line->quantity,
                'uom_id'     => (int) $line->uom_id,
            ])
            ->sortBy('product_id')
            ->values()
            ->all();
    }

    private function sameRecipe(?array $a, ?array $b): bool
    {
        return $this->normalize($a) === $this->normalize($b);
    }

    /** Normalize types so a JSON round-trip (int vs float) never looks like a change. */
    private function normalize(?array $recipe): string
    {
        $rows = array_map(fn ($row) => [
            (int) ($row['product_id'] ?? 0),
            round((float) ($row['quantity'] ?? 0), 4),
            (int) ($row['uom_id'] ?? 0),
        ], $recipe ?? []);

        return (string) json_encode($rows);
    }
}
