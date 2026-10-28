<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialRequest extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'submitted', 'approved', 'rejected', 'partially_issued', 'issued', 'cancelled'];

    protected $fillable = ['required_date', 'reason'];

    protected function casts(): array
    {
        return ['required_date' => 'date:Y-m-d', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialRequestItem::class);
    }

    public function scopeForCompany(Builder $q, int $id): Builder
    {
        return $q->where('company_id', $id);
    }
}
