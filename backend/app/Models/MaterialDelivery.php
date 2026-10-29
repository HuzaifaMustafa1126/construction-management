<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialDelivery extends Model
{
    use BelongsToCompany, SoftDeletes;

    public const STATUSES = ['draft', 'received', 'cancelled'];

    protected $fillable = ['supplier_id', 'supplier_invoice_number', 'delivery_date', 'vehicle_number', 'delivery_note'];

    protected function casts(): array
    {
        return ['delivery_date' => 'date:Y-m-d', 'received_at' => 'datetime', 'submitted_at' => 'datetime', 'posted_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialDeliveryItem::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForCompany(Builder $q, int $id): Builder
    {
        return $q->where('company_id', $id);
    }
}
