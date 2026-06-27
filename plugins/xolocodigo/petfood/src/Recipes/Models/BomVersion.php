<?php

namespace XoloCodigo\PetFood\Recipes\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Manufacturing\Models\BillOfMaterial;

/**
 * An immutable snapshot of a bill of materials' recipe, with a validity window.
 *
 * @property int $bill_of_material_id
 * @property int $version
 * @property array $recipe
 * @property int|null $changed_by
 */
class BomVersion extends Model
{
    protected $table = 'petfood_bom_versions';

    protected $fillable = [
        'bill_of_material_id',
        'version',
        'effective_from',
        'effective_to',
        'recipe',
        'changed_by',
    ];

    protected $casts = [
        'recipe'         => 'array',
        'effective_from' => 'datetime',
        'effective_to'   => 'datetime',
    ];

    public function billOfMaterial(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterial::class, 'bill_of_material_id');
    }
}
