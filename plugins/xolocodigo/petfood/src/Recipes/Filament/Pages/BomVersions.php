<?php

namespace XoloCodigo\PetFood\Recipes\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Webkul\Product\Models\Product;
use Webkul\Support\Models\UOM;
use XoloCodigo\PetFood\Recipes\Models\BomVersion;

/**
 * Read-only history of recipe (BOM) versions: which formula was in effect when, who changed
 * it and what changed against the previous version. Reads the immutable versions captured
 * automatically when recipes change.
 */
class BomVersions extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Calidad e Inocuidad';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Control de cambios de receta';

    protected static ?string $title = 'Control de cambios de receta';

    protected string $view = 'petfood::filament.pages.bom-versions';

    /** @var array<int, array{bom:string, version:int, from:?string, to:?string, current:bool, lines:int, by:?string, changes:list<array{kind:string, text:string}>}> */
    public array $versions = [];

    public function mount(): void
    {
        // Versions carry no company of their own: they are visible only through a recipe the company scope lets through.
        $versions = BomVersion::query()
            ->whereHas('billOfMaterial')
            ->with(['billOfMaterial.product', 'changedBy'])
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->limit(50)
            ->get();

        // Every version of the listed recipes, so each one is compared with its predecessor.
        $all = BomVersion::query()->whereIn('bill_of_material_id', $versions->pluck('bill_of_material_id'))->get()
            ->keyBy(fn (BomVersion $v): string => "{$v->bill_of_material_id}.{$v->version}");
        $components = $all->flatMap(fn (BomVersion $v): array => $v->recipe ?? []);
        $products = Product::withTrashed()->whereIn('id', $components->pluck('product_id'))->pluck('name', 'id');
        $uoms = UOM::query()->whereIn('id', $components->pluck('uom_id'))->pluck('name', 'id');

        $this->versions = $versions
            ->map(function (BomVersion $v) use ($all, $products, $uoms): array {
                $previous = $all->get("{$v->bill_of_material_id}.".($v->version - 1));

                return [
                    'bom'     => $v->billOfMaterial?->product?->name
                        ?: ($v->billOfMaterial?->code ?: 'BOM #'.$v->bill_of_material_id),
                    'version' => $v->version,
                    'from'    => $v->effective_from?->format('d/m/Y H:i'),
                    'to'      => $v->effective_to?->format('d/m/Y H:i'),
                    'current' => $v->effective_to === null,
                    'lines'   => count($v->recipe ?? []),
                    'by'      => $v->changedBy?->name,
                    'changes' => $previous ? $this->changes($previous->recipe ?? [], $v->recipe ?? [], $products, $uoms) : [],
                ];
            })
            ->all();
    }

    /**
     * Component-level difference between two recipe snapshots.
     *
     * @param  array<int, array{product_id:int, quantity:float, uom_id:int}>  $before
     * @param  array<int, array{product_id:int, quantity:float, uom_id:int}>  $after
     * @return list<array{kind:'added'|'removed'|'changed', text:string}>
     */
    private function changes(array $before, array $after, Collection $products, Collection $uoms): array
    {
        // A recipe may list a component more than once: compare its total.
        $byComponent = fn (array $recipe): Collection => collect($recipe)->groupBy('product_id')
            ->map(fn (Collection $rows): array => ['quantity' => round($rows->sum('quantity'), 4), 'uom_id' => $rows->first()['uom_id']]);
        $old = $byComponent($before);
        $new = $byComponent($after);

        $name = fn ($id): string => $products[$id] ?? "Producto #{$id}";
        $amount = fn (array $row): string => rtrim(rtrim(number_format($row['quantity'], 4, '.', ','), '0'), '.').' '.($uoms[$row['uom_id']] ?? '');

        return $new->diffKeys($old)->map(fn (array $row, $id): array => ['kind' => 'added', 'text' => $name($id).' '.$amount($row)])
            ->concat($old->diffKeys($new)->map(fn (array $row, $id): array => ['kind' => 'removed', 'text' => $name($id).' '.$amount($row)]))
            ->concat($new->intersectByKeys($old)->filter(fn (array $row, $id): bool => $row != $old[$id])
                ->map(fn (array $row, $id): array => ['kind' => 'changed', 'text' => $name($id).': '.$amount($old[$id]).' → '.$amount($row)]))
            ->values()
            ->all();
    }
}
