<?php

namespace App\Models;

use App\Support\Authorization\PermissionCatalog;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasApiTokens, HasFactory, HasRoles, MustVerifyEmailTrait, Notifiable, SoftDeletes {
        HasRoles::hasPermissionTo as private hasPermissionToWithoutCompatibility;
    }

    protected string $guard_name = 'web';

    public const STATUS_ACTIVE = 'Active';

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'department',
        'department_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'company_id',
    ];

    protected function casts(): array
    {
        return [
            'is_platform_admin' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isPlatformAdmin(): bool
    {
        return $this->is_platform_admin === true && $this->company_id === null;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id')->withTrashed();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withPivot([
            'id', 'company_id', 'assignment_type', 'assigned_by', 'assigned_at',
        ])->withTimestamps();
    }

    public function createdDailySiteReports(): HasMany
    {
        return $this->hasMany(DailySiteReport::class, 'created_by');
    }

    public function uploadedSitePhotos(): HasMany
    {
        return $this->hasMany(SitePhoto::class, 'uploaded_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        if (! is_string($permission)) {
            return $this->hasPermissionToWithoutCompatibility($permission, $guardName);
        }

        foreach (PermissionCatalog::equivalents($permission) as $equivalent) {
            try {
                if ($this->hasPermissionToWithoutCompatibility($equivalent, $guardName)) {
                    return true;
                }
            } catch (PermissionDoesNotExist) {
                // Compatibility must tolerate one side of an alias not being defined yet.
            }
        }

        return false;
    }
}
