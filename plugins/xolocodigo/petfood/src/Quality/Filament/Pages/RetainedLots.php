<?php

namespace XoloCodigo\PetFood\Quality\Filament\Pages;

use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Webkul\Inventory\Enums\LocationType;
use Webkul\Inventory\Enums\MoveState;
use Webkul\Inventory\Models\Location;
use Webkul\Inventory\Models\MoveLine;
use Webkul\Inventory\Models\ProductQuantity;
use XoloCodigo\PetFood\Quality\Enums\MxQualityRole;
use XoloCodigo\PetFood\Quality\Services\QualityLocationService;

/**
 * Lots held by quality (quarantine / rejected / waste): what, how much, since when, why and
 * who, with the actions to retain, release, reject or send to waste. Every action is a real
 * stock move (QualityLocationService::transfer), so it stays in the lot's history.
 */
class RetainedLots extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string|\UnitEnum|null $navigationGroup = 'Calidad e Inocuidad';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Lotes retenidos';

    protected static ?string $title = 'Lotes retenidos';

    protected string $view = 'petfood::filament.pages.retained-lots';

    /** @var array<int, ?MoveLine> last move line that brought each retained stock row in */
    protected array $entries = [];

    public function table(Table $table): Table
    {
        return $table
            ->query($this->quality()->retainedStock()->with(['lot', 'product.uom', 'location']))
            ->defaultSort('location_id')
            ->emptyStateHeading('No hay lotes retenidos')
            ->emptyStateDescription('Usa “Retener lote” cuando un lote deba esperar análisis o no se pueda usar.')
            ->emptyStateIcon('heroicon-o-shield-check')
            ->columns([
                TextColumn::make('lot.name')->label('Lote')->weight('bold')->searchable(),
                TextColumn::make('product.name')->label('Producto')->searchable(),
                TextColumn::make('area')->label('Área')->badge()
                    ->state(fn (ProductQuantity $record): string => $this->role($record)->label())
                    ->color(fn (ProductQuantity $record): string => match ($this->role($record)) {
                        MxQualityRole::Quarantine => 'warning',
                        MxQualityRole::Rejected   => 'danger',
                        MxQualityRole::Waste      => 'gray',
                    }),
                TextColumn::make('quantity')->label('Cantidad')->alignEnd()
                    ->formatStateUsing(fn ($state, ProductQuantity $record): string => $this->qty($state, $record)),
                TextColumn::make('since')->label('Desde')
                    ->state(fn (ProductQuantity $record) => $this->entry($record)?->scheduled_at ?? $record->created_at)
                    ->date('d/m/Y'),
                TextColumn::make('reason')->label('Motivo')->placeholder('—')->wrap()
                    ->state(fn (ProductQuantity $record): ?string => $this->entry($record)?->move?->origin),
                TextColumn::make('responsible')->label('Responsable')->placeholder('—')
                    ->state(fn (ProductQuantity $record): ?string => $this->entry($record)?->move?->creator?->name),
            ])
            ->recordActions([
                $this->moveAction('release', 'Liberar', 'success', 'heroicon-o-check-circle', [MxQualityRole::Quarantine],
                    fn (ProductQuantity $record): Location => $record->location->warehouse->lotStockLocation),
                $this->moveAction('reject', 'Rechazar', 'danger', 'heroicon-o-x-circle', [MxQualityRole::Quarantine],
                    fn (ProductQuantity $record): Location => $this->quality()->locationFor($record->location->warehouse, MxQualityRole::Rejected)),
                $this->moveAction('waste', 'Mandar a merma', 'gray', 'heroicon-o-trash', [MxQualityRole::Quarantine, MxQualityRole::Rejected],
                    fn (ProductQuantity $record): Location => $this->quality()->locationFor($record->location->warehouse, MxQualityRole::Waste)),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retain')
                ->label('Retener lote')
                ->icon('heroicon-o-shield-exclamation')
                ->color('warning')
                ->modalDescription('El material pasa al área de calidad y deja de estar disponible para producción y venta.')
                ->schema([
                    Select::make('stock_id')->label('Lote')->required()->searchable()->live()
                        ->options(fn (): array => $this->usableStock()->mapWithKeys(fn (ProductQuantity $stock): array => [
                            $stock->id => "{$stock->lot->name} · {$stock->product->name} · disponible ".$this->qty($stock->available_quantity, $stock),
                        ])->all()),
                    Select::make('area')->label('Área')->required()->default(MxQualityRole::Quarantine->value)
                        ->options(collect(MxQualityRole::cases())->mapWithKeys(fn (MxQualityRole $role): array => [$role->value => $role->label()])),
                    $this->quantityInput(fn (Get $get): float => (float) ProductQuantity::query()->find($get('stock_id'))?->available_quantity),
                    $this->reasonInput(),
                ])
                ->action(function (array $data): void {
                    $stock = $this->usableStock()->firstWhere('id', (int) $data['stock_id']);
                    $destination = $this->quality()->locationFor($stock->location->warehouse, MxQualityRole::from($data['area']));

                    $this->quality()->transfer($stock, $destination, (float) $data['quantity'], $data['reason']);

                    Notification::make()->success()->title("Lote {$stock->lot->name} retenido en {$destination->name}")->send();
                }),
        ];
    }

    /**
     * A row action that moves (part of) a retained lot to another location.
     *
     * @param  list<MxQualityRole>  $from  areas where the action is offered
     * @param  Closure(ProductQuantity): Location  $destination
     */
    private function moveAction(string $name, string $label, string $color, string $icon, array $from, Closure $destination): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($color)
            ->icon($icon)
            ->visible(fn (ProductQuantity $record): bool => in_array($this->role($record), $from, true))
            ->modalHeading(fn (ProductQuantity $record): string => "{$label}: lote {$record->lot?->name}")
            ->schema(fn (ProductQuantity $record): array => [
                $this->quantityInput(fn (): float => (float) $record->available_quantity)->default((float) $record->available_quantity),
                $this->reasonInput(),
            ])
            ->action(function (ProductQuantity $record, array $data) use ($label, $destination): void {
                $this->quality()->transfer($record, $destination($record), (float) $data['quantity'], $data['reason']);

                Notification::make()->success()->title("{$label}: lote {$record->lot?->name} listo")->send();
            });
    }

    /** @param  Closure(...): float  $available */
    private function quantityInput(Closure $available): TextInput
    {
        return TextInput::make('quantity')->label('Cantidad')->required()->numeric()
            ->rule('gt:0')
            ->maxValue($available);
    }

    private function reasonInput(): Textarea
    {
        return Textarea::make('reason')->label('Motivo')->required()->maxLength(255)->rows(2)
            ->placeholder('Ej. humedad fuera de especificación, en espera de laboratorio');
    }

    /** Lot stock that can be retained: usable internal locations of a warehouse, unreserved quantity left. */
    private function usableStock()
    {
        return ProductQuantity::query()
            ->with(['lot', 'product.uom', 'location'])
            ->whereNotNull('lot_id')
            ->whereColumn('quantity', '>', 'reserved_quantity')
            ->whereHas('location', fn ($location) => $location
                ->where('type', LocationType::INTERNAL)
                ->where('is_scrap', false)
                ->whereNotNull('warehouse_id'))
            ->get();
    }

    private function entry(ProductQuantity $record): ?MoveLine
    {
        return $this->entries[$record->id] ??= MoveLine::query()
            ->with('move.creator')
            ->where('lot_id', $record->lot_id)
            ->where('destination_location_id', $record->location_id)
            ->where('state', MoveState::DONE)
            ->latest('id')
            ->first();
    }

    private function role(ProductQuantity $record): MxQualityRole
    {
        return MxQualityRole::from($record->location->mx_quality_role);
    }

    private function qty(mixed $value, ProductQuantity $record): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ','), '0'), '.').' '.$record->product?->uom?->name;
    }

    private function quality(): QualityLocationService
    {
        return app(QualityLocationService::class);
    }
}
