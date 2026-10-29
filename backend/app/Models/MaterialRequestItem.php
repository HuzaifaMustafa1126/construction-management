<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialRequestItem extends Model
{
    use BelongsToCompany;

    protected $fillable = ['project_inventory_id', 'quantity_requested', 'notes'];

    protected function casts(): array
    {
        return ['quantity_requested' => 'decimal:4', 'quantity_approved' => 'decimal:4', 'quantity_issued' => 'decimal:4', 'quantity_returned' => 'decimal:4'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class, 'material_request_id');
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(ProjectInventory::class, 'project_inventory_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class)->withTrashed();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }
}
