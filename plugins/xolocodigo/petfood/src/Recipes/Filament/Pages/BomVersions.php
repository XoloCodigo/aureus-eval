<?php

namespace XoloCodigo\PetFood\Recipes\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use XoloCodigo\PetFood\Recipes\Models\BomVersion;

/**
 * Read-only history of recipe (BOM) versions: which formula was in effect when,
 * how many components it had, and whether it is the current one. Reads the
 * immutable versions captured automatically when recipes change.
 */
class BomVersions extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Calidad e Inocuidad';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Control de cambios de receta';

    protected static ?string $title = 'Control de cambios de receta';

    protected string $view = 'petfood::filament.pages.bom-versions';

    /** @var array<int, array{bom:string, version:int, from:?string, to:?string, current:bool, lines:int}> */
    public array $versions = [];

    public function mount(): void
    {
        $this->versions = BomVersion::query()
            ->with('billOfMaterial.product')
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->limit(50)
            ->get()
            ->map(fn (BomVersion $v) => [
                'bom'     => $v->billOfMaterial?->product?->name
                    ?: ($v->billOfMaterial?->code ?: 'BOM #'.$v->bill_of_material_id),
                'version' => $v->version,
                'from'    => $v->effective_from?->format('Y-m-d H:i'),
                'to'      => $v->effective_to?->format('Y-m-d H:i'),
                'current' => $v->effective_to === null,
                'lines'   => count($v->recipe ?? []),
            ])
            ->all();
    }
}
