<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    use BelongsToCompany;

    public const TYPES = ['opening_stock', 'material_received', 'material_consumed', 'adjustment_in', 'adjustment_out', 'material_wastage', 'inventory_correction_in', 'inventory_correction_out', 'material_issued', 'material_returned', 'transfer_out', 'transfer_in'];

    public const DIRECTIONS = ['in', 'out'];

    protected $fillable = ['transaction_date', 'notes'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'transaction_date' => 'date:Y-m-d'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projectInventory(): BelongsTo
    {
        return $this->belongsTo(ProjectInventory::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveryItem(): BelongsTo
    {
        return $this->belongsTo(MaterialDeliveryItem::class);
    }

    public function consumptionItem(): BelongsTo
    {
        return $this->belongsTo(MaterialConsumptionItem::class);
    }

    public function stockAdjustmentItem(): BelongsTo
    {
        return $this->belongsTo(MaterialStockAdjustmentItem::class);
    }

    public function materialRequestItem(): BelongsTo
    {
        return $this->belongsTo(MaterialRequestItem::class);
    }
}
