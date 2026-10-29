<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialDeliveryItem extends Model
{
    use BelongsToCompany;

    protected $fillable = ['project_inventory_id', 'quantity', 'notes'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(MaterialDelivery::class, 'material_delivery_id');
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
        return $this->hasMany(InventoryTransaction::class, 'delivery_item_id');
    }
}
