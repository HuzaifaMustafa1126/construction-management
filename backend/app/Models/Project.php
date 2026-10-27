<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    public const STATUSES = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];

    public const ASSIGNMENT_TYPES = ['member', 'supervisor', 'client'];

    protected $fillable = [
        'name', 'code', 'description', 'location', 'address', 'city', 'state', 'country',
        'postal_code', 'start_date', 'expected_end_date', 'actual_end_date', 'budget',
        'progress_percentage', 'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'expected_end_date' => 'date:Y-m-d',
            'actual_end_date' => 'date:Y-m-d',
            'budget' => 'decimal:2',
            'progress_percentage' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot([
            'id', 'company_id', 'assignment_type', 'assigned_by', 'assigned_at',
        ])->withTimestamps();
    }

    public function dailySiteReports(): HasMany
    {
        return $this->hasMany(DailySiteReport::class);
    }

    public function sitePhotos(): HasMany
    {
        return $this->hasMany(SitePhoto::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(ProjectInventory::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function materialDeliveries(): HasMany
    {
        return $this->hasMany(MaterialDelivery::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('company_admin')) {
            return $query;
        }

        return $query->whereHas('members', fn (Builder $members) => $members->where('users.id', $user->id));
    }
}
