<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    public const TYPES = ['opening_stock'];

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
}
