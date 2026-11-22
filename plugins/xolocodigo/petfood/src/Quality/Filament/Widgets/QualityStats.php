<?php

namespace XoloCodigo\PetFood\Quality\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Webkul\Manufacturing\Enums\ManufacturingOrderState;
use Webkul\Manufacturing\Models\Order;
use XoloCodigo\PetFood\Quality\Filament\Pages\QualityOverview;
use XoloCodigo\PetFood\Quality\Filament\Pages\RetainedLots;
use XoloCodigo\PetFood\Quality\Services\QualityLocationService;

/**
 * The KPI cards of the quality overview. Not discovered by the panel, so it only
 * shows on QualityOverview, never on the main dashboard.
 */
class QualityStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $expired = QualityOverview::expiringLots()->where('expiration_date', '<', now())->count();

        return [
            Stat::make('Lotes retenidos', app(QualityLocationService::class)->retainedStock()->count())
                ->description('En cuarentena, rechazo o merma')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color('warning')
                ->url(RetainedLots::getUrl()),
            Stat::make('Por vencer en 30 días', QualityOverview::expiringLots()->count())
                ->description(match ($expired) {
                    0       => 'Lotes con existencia en almacén',
                    1       => '1 ya vencido',
                    default => "{$expired} ya vencidos",
                })
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($expired ? 'danger' : 'gray'),
            Stat::make('Producción del mes', Order::query()
                ->where('state', ManufacturingOrderState::DONE)
                ->whereBetween('finished_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count())
                ->description('Órdenes de fabricación terminadas en '.now()->translatedFormat('F'))
                ->descriptionIcon('heroicon-m-cog-6-tooth')
                ->color('success'),
        ];
    }
}
