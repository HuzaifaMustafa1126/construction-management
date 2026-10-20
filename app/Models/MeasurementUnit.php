<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeasurementUnit extends Model
{
    use SoftDeletes;

    public const TYPES = ['count', 'weight', 'volume', 'area', 'length'];

    protected $fillable = ['name', 'symbol', 'unit_type', 'decimal_precision', 'is_active'];

    protected function casts(): array
    {
        return ['decimal_precision' => 'integer', 'is_system' => 'boolean', 'is_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function scopeAvailableTo(Builder $query, int $companyId): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('is_system', true)->whereNull('company_id')->orWhere('company_id', $companyId));
    }
}
