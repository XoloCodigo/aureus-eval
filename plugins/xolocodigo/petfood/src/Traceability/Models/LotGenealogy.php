<?php

namespace XoloCodigo\PetFood\Traceability\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Inventory\Models\Lot;
use Webkul\Inventory\Models\MoveLine;
use Webkul\Manufacturing\Models\Order;
use Webkul\Product\Models\Product;
use Webkul\Support\Models\Company;
use Webkul\Support\Models\UOM;
use XoloCodigo\PetFood\Database\Factories\LotGenealogyFactory;

class LotGenealogy extends Model
{
    use HasFactory;

    protected $table = 'petfood_lot_genealogies';

    protected $fillable = [
        'manufacturing_order_id',
        'produced_lot_id',
        'produced_product_id',
        'consumed_lot_id',
        'consumed_product_id',
        'quantity',
        'uom_id',
        'consumed_move_line_id',
        'company_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    public function manufacturingOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'manufacturing_order_id');
    }

    public function producedLot(): BelongsTo
    {
        return $this->belongsTo(Lot::class, 'produced_lot_id');
    }

    public function consumedLot(): BelongsTo
    {
        return $this->belongsTo(Lot::class, 'consumed_lot_id');
    }

    public function producedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'produced_product_id');
    }

    public function consumedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'consumed_product_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UOM::class, 'uom_id');
    }

    public function consumedMoveLine(): BelongsTo
    {
        return $this->belongsTo(MoveLine::class, 'consumed_move_line_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected static function newFactory(): LotGenealogyFactory
    {
        return LotGenealogyFactory::new();
    }
}
