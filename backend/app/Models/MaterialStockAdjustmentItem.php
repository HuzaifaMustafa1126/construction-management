<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialStockAdjustmentItem extends Model
{
    use BelongsToCompany;

    public const TYPES = ['adjustment_in', 'adjustment_out', 'material_wastage', 'inventory_correction_in', 'inventory_correction_out'];

    public const REASON_CODES = ['physical_count_difference', 'damage', 'breakage', 'theft', 'expiry', 'site_loss', 'recording_error', 'other'];

    public const CORRECTION_TYPES = ['inventory_correction_in', 'inventory_correction_out'];

    public const OUT_TYPES = ['adjustment_out', 'material_wastage', 'inventory_correction_out'];

    protected $fillable = ['project_inventory_id', 'adjustment_type', 'quantity', 'reason_code', 'notes', 'correction_explanation', 'reference_inventory_transaction_id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(MaterialStockAdjustment::class, 'material_stock_adjustment_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class)->withTrashed();
    }

    public function projectInventory(): BelongsTo
    {
        return $this->belongsTo(ProjectInventory::class)->withTrashed();
    }

    public function referenceTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'reference_inventory_transaction_id');
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'stock_adjustment_item_id');
    }

    public static function direction(string $type): string
    {
        return in_array($type, self::OUT_TYPES, true) ? 'out' : 'in';
    }
}
