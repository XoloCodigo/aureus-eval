<?php

namespace XoloCodigo\PetFood\Traceability\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Inventory\Models\Lot;
use Webkul\Inventory\Models\ProductQuantity;
use Webkul\Manufacturing\Models\Order;
use Webkul\Partner\Models\Partner;
use Webkul\Product\Models\Product;
use XoloCodigo\PetFood\Enums\MxItemType;
use XoloCodigo\PetFood\Quality\Enums\MxQualityRole;
use XoloCodigo\PetFood\Quality\Services\QualityLocationService;
use XoloCodigo\PetFood\Traceability\Models\LotGenealogy;
use XoloCodigo\PetFood\Traceability\Services\LotTraceabilityService;

/**
 * Lot tracing (recall) for the quality team: search a lot, see its card, where it
 * was used (towards customers), what it is made of (towards suppliers), the
 * affected customers, and download the recall report. Presentation only: the
 * genealogy comes from LotTraceabilityService.
 */
class LotTraceability extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Calidad e Inocuidad';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Rastreo de lotes';

    protected static ?string $title = 'Rastreo de lotes';

    protected string $view = 'petfood::filament.pages.lot-traceability';

    /** @var array{lot_id?: int|string|null} */
    public ?array $data = [];

    /** @var list<array<string, mixed>> lots produced with the selected lot (towards customers) */
    public array $usedIn = [];

    /** @var list<array<string, mixed>> lots consumed to produce the selected lot (towards suppliers) */
    public array $madeOf = [];

    /** @var list<array<string, mixed>> */
    public array $affectedCustomers = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('lot_id')
                    ->hiddenLabel()
                    ->placeholder('Busca por lote o producto…')
                    ->searchable()
                    ->options(fn (): array => $this->lotOptions())
                    ->getSearchResultsUsing(fn (string $search): array => $this->lotOptions($search))
                    ->getOptionLabelUsing(fn ($value): ?string => $this->label(Lot::with('product')->find($value)))
                    ->live()
                    ->afterStateUpdated(fn () => $this->trace()),
            ]);
    }

    /**
     * Without a search: lots that already have genealogy first, grouped. With a search: a flat
     * list matching the lot or the product name.
     *
     * @return array<string, array<int, string>>|array<int, string>
     */
    public function lotOptions(?string $search = null): array
    {
        $lots = Lot::query()
            ->with('product')
            ->when($search, fn ($query) => $query->where(fn ($match) => $match
                ->where('name', 'like', "%{$search}%")
                ->orWhereHas('product', fn ($product) => $product->where('name', 'like', "%{$search}%"))))
            ->orderByDesc('id')
            ->limit($search ? 50 : 200)
            ->get();

        $labels = fn ($group): array => $group->mapWithKeys(fn (Lot $lot): array => [$lot->id => $this->label($lot)])->all();

        if ($search) {
            return $labels($lots);
        }

        $traced = LotGenealogy::query()->pluck('produced_lot_id')
            ->merge(LotGenealogy::query()->pluck('consumed_lot_id'))
            ->flip();

        [$withHistory, $unused] = $lots->partition(fn (Lot $lot): bool => $traced->has($lot->id));

        return array_filter([
            'Con trazabilidad' => $labels($withHistory),
            'Sin uso todavía'  => $labels($unused),
        ]);
    }

    public function trace(): void
    {
        $lot = $this->selectedLot();
        $service = app(LotTraceabilityService::class);
        $uom = $lot?->product?->uom?->name;

        $this->usedIn = $lot ? $this->describe($service->traceBackward($lot)->all(), $uom) : [];
        $this->madeOf = $lot ? $this->describe($service->traceForward($lot)->all()) : [];
        $this->affectedCustomers = $lot ? $service->affectedCustomers($lot)->all() : [];
    }

    /**
     * @return array<string, ?string>|null
     */
    public function getLotCardProperty(): ?array
    {
        $lot = $this->selectedLot();

        if (! $lot) {
            return null;
        }

        $uom = $lot->product?->uom?->name;
        $areas = app(QualityLocationService::class)->qualityLocations()->keyBy('id');

        $retained = ProductQuantity::query()
            ->where('lot_id', $lot->id)
            ->whereIn('location_id', $areas->keys())
            ->where('quantity', '>', 0)
            ->get()
            ->map(fn (ProductQuantity $quant): string => MxQualityRole::from($areas[$quant->location_id]->mx_quality_role)->label()
                .': '.$this->qty($quant->quantity, $uom))
            ->implode(', ');

        return [
            'Producto'            => $lot->product?->name,
            'Tipo'                => MxItemType::tryFrom((string) $lot->product?->mx_item_type)?->label(),
            'Proveedor'           => $lot->mx_supplier_id ? Partner::query()->find($lot->mx_supplier_id)?->name : null,
            'Lote del proveedor'  => $lot->mx_supplier_lot,
            'Producido en'        => Order::query()->where('producing_lot_id', $lot->id)->value('name'),
            'Caducidad'           => $lot->expiration_date?->format('d/m/Y'),
            'Disponible'          => $this->qty($lot->total_quantity, $uom),
            'Retenido en calidad' => $retained ?: null,
        ];
    }

    public function getLotNameProperty(): ?string
    {
        return $this->selectedLot()?->name;
    }

    public function getRecallSummaryProperty(): ?string
    {
        if (! $this->selectedLot()) {
            return null;
        }

        $customers = collect($this->affectedCustomers)->pluck('customer_id')->unique()->count();
        $clients = $customers === 1 ? 'cliente' : 'clientes';

        if ($this->usedIn === []) {
            return $customers
                ? "Este lote llegó directo a {$customers} {$clients}."
                : 'Este lote todavía no se usa en ninguna producción ni se ha entregado a clientes.';
        }

        $byType = collect($this->usedIn)->countBy('type');
        $parts = array_filter([
            ($byType['wip'] ?? 0) ? $byType['wip'].' de premezcla' : null,
            ($byType['finished_good'] ?? 0) ? $byType['finished_good'].' de producto terminado' : null,
        ]);
        $lots = count($this->usedIn);

        return sprintf(
            'Este lote se usó en %d %s%s y llegó a %d %s.',
            $lots,
            $lots === 1 ? 'lote' : 'lotes',
            $parts ? ' ('.implode(' y ', $parts).')' : '',
            $customers,
            $clients,
        );
    }

    public function qty(mixed $value, ?string $uom = null): string
    {
        return trim(rtrim(rtrim(number_format((float) $value, 2, '.', ','), '0'), '.').' '.$uom);
    }

    public function itemTypeLabel(?string $type): ?string
    {
        return MxItemType::tryFrom((string) $type)?->label();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportRecall')
                ->label('Descargar reporte de recall')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => $this->selectedLot() !== null)
                ->action(fn (): StreamedResponse => $this->recallCsv()),
        ];
    }

    private function selectedLot(): ?Lot
    {
        $id = $this->data['lot_id'] ?? null;

        return filled($id) ? Lot::with('product.uom')->find($id) : null;
    }

    private function label(?Lot $lot): ?string
    {
        return $lot ? $lot->name.' · '.($lot->product?->name ?? 'sin producto') : null;
    }

    /**
     * Readable genealogy nodes. A node's quantity is of the lot consumed to make it, so for
     * "used in" it is measured in the parent's unit (the previous level, or the selected lot).
     *
     * @param  array<int, array{lot_id:int, product_id:int, manufacturing_order_id:int, quantity:float, depth:int}>  $nodes
     * @return list<array<string, mixed>>
     */
    private function describe(array $nodes, ?string $rootUom = null): array
    {
        $lots = Lot::query()->whereIn('id', array_column($nodes, 'lot_id'))->pluck('name', 'id');
        $products = Product::query()->with('uom')->whereIn('id', array_column($nodes, 'product_id'))->get()->keyBy('id');
        $orders = Order::query()->whereIn('id', array_column($nodes, 'manufacturing_order_id'))->pluck('name', 'id');
        $parentUom = [1 => $rootUom];

        return array_map(function (array $node) use ($lots, $products, $orders, &$parentUom, $rootUom): array {
            $product = $products[$node['product_id']] ?? null;
            $parentUom[$node['depth'] + 1] = $product?->uom?->name;

            return $node + [
                'lot_name'     => $lots[$node['lot_id']] ?? '#'.$node['lot_id'],
                'product_name' => $product?->name ?? '#'.$node['product_id'],
                'type'         => $product?->mx_item_type,
                'uom'          => $rootUom !== null ? ($parentUom[$node['depth']] ?? null) : $product?->uom?->name,
                'order'        => $orders[$node['manufacturing_order_id']] ?? null,
            ];
        }, $nodes);
    }

    private function recallCsv(): StreamedResponse
    {
        $card = ['Lote' => $this->lotName] + $this->lotCard;

        return response()->streamDownload(function () use ($card): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            foreach ($card as $field => $value) {
                fputcsv($out, [$field, $value]);
            }

            fputcsv($out, []);
            fputcsv($out, ['¿En qué se usó?']);
            fputcsv($out, ['Nivel', 'Lote', 'Producto', 'Cantidad usada', 'Unidad', 'Orden de fabricación']);

            foreach ($this->usedIn as $node) {
                fputcsv($out, [$node['depth'], $node['lot_name'], $node['product_name'], $node['quantity'], $node['uom'], $node['order']]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Clientes afectados']);
            fputcsv($out, ['Cliente', 'Lote entregado', 'Producto', 'Cantidad', 'Unidad', 'Fecha', 'Entrega']);

            foreach ($this->affectedCustomers as $row) {
                fputcsv($out, [
                    $row['customer'], $row['lot'], $row['product'], $row['quantity'], $row['uom'],
                    $row['shipped_at'] ? Carbon::parse($row['shipped_at'])->format('d/m/Y') : '', $row['reference'],
                ]);
            }

            fclose($out);
        }, "recall-{$card['Lote']}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
