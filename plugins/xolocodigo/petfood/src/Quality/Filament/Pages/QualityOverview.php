<?php

namespace XoloCodigo\PetFood\Quality\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Webkul\Inventory\Models\Lot;
use XoloCodigo\PetFood\Quality\Filament\Widgets\QualityStats;

/**
 * Landing page of "Calidad e Inocuidad": the KPI cards (QualityStats) and the lots that
 * expire within 30 days, or already expired, while they still have stock.
 */
class QualityOverview extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Calidad e Inocuidad';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Resumen';

    protected static ?string $title = 'Resumen de calidad';

    protected string $view = 'petfood::filament.pages.quality-overview';

    /** Lots with usable stock whose expiration date is within 30 days or already past. */
    public static function expiringLots(): Builder
    {
        return Lot::query()
            ->where('expiration_date', '<=', now()->addDays(30))
            ->whereHas('internalQuantities', fn (Builder $quantity) => $quantity->where('quantity', '>', 0));
    }

    protected function getHeaderWidgets(): array
    {
        return [QualityStats::class];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(static::expiringLots()->with('product.uom')->withSum('internalQuantities as total_quantity', 'quantity'))
            ->defaultSort('expiration_date')
            ->heading('Lotes por vencer')
            ->description('Con existencia en almacén y caducidad en los próximos 30 días, o ya vencidos.')
            ->emptyStateHeading('Ningún lote vence en los próximos 30 días')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('name')->label('Lote')->weight('bold')->searchable(),
                TextColumn::make('product.name')->label('Producto')->searchable(),
                TextColumn::make('expiration_date')->label('Caducidad')->date('d/m/Y')
                    ->color(fn (Lot $record): string => $record->expiration_date->isPast() ? 'danger' : 'warning')
                    ->description(fn (Lot $record): string => ($record->expiration_date->isPast() ? 'Vencido ' : 'Vence ').$record->expiration_date->diffForHumans()),
                TextColumn::make('total_quantity')->label('Existencia')->alignEnd()
                    ->formatStateUsing(fn ($state, Lot $record): string => rtrim(rtrim(number_format((float) $state, 2, '.', ','), '0'), '.').' '.$record->product?->uom?->name),
            ]);
    }
}
