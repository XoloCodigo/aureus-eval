<?php

namespace XoloCodigo\PetFood\Quality\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Webkul\Inventory\Models\ProductQuantity;
use XoloCodigo\PetFood\Quality\Enums\MxQualityRole;
use XoloCodigo\PetFood\Quality\Services\QualityLocationService;

/**
 * Read-only overview of the quality areas (quarantine / rejected / waste) and
 * how much material each one is currently retaining.
 *
 * Consumes QualityLocationService for the locations; the retained amount is read
 * from the core ProductQuantity. Presentation only — no core resources touched.
 */
class QualityLocations extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationLabel = 'Ubicaciones de calidad';

    protected static ?string $title = 'Ubicaciones de calidad';

    protected string $view = 'petfood::filament.pages.quality-locations';

    /** @var array<int, array{label:string, locations:array<int, array{name:string, retained:float}>}> */
    public array $groups = [];

    public function mount(QualityLocationService $service): void
    {
        foreach (MxQualityRole::cases() as $role) {
            $locations = $service->qualityLocations($role)
                ->map(fn ($location) => [
                    'name'     => $location->full_name ?: $location->name,
                    'retained' => (float) ProductQuantity::query()
                        ->where('location_id', $location->id)
                        ->sum('quantity'),
                ])
                ->all();

            $this->groups[] = [
                'label'     => $role->label(),
                'locations' => $locations,
            ];
        }
    }
}
