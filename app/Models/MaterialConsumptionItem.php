<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialConsumptionItem extends Model
{
    protected $fillable = ['project_inventory_id', 'quantity', 'notes'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    public function consumption(): BelongsTo
    {
        return $this->belongsTo(MaterialConsumption::class, 'material_consumption_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class)->withTrashed();
    }

    public function projectInventory(): BelongsTo
    {
        return $this->belongsTo(ProjectInventory::class)->withTrashed();
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'consumption_item_id');
    }
}
