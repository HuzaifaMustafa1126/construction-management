<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const SUBSCRIPTION_STATUS_TRIAL = 'trial';

    public const SUBSCRIPTION_STATUS_ACTIVE = 'active';

    public const SUBSCRIPTION_STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'website',
        'currency',
        'timezone',
        'subscription_plan_id',
        'subscription_status',
        'trial_ends_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function dailySiteReports(): HasMany
    {
        return $this->hasMany(DailySiteReport::class);
    }

    public function isOperational(): bool
    {
        return ! $this->trashed() && $this->status === self::STATUS_ACTIVE;
    }
}
