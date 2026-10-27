<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SitePhoto extends Model
{
    use SoftDeletes;

    public const TIME_CATEGORIES = ['morning', 'afternoon', 'evening'];

    public const APPROVAL_STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = ['caption', 'description', 'photo_date', 'time_category', 'daily_site_report_id'];

    protected function casts(): array
    {
        return [
            'photo_date' => 'date:Y-m-d', 'file_size' => 'integer',
            'is_client_visible' => 'boolean', 'reviewed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function dailySiteReport(): BelongsTo
    {
        return $this->belongsTo(DailySiteReport::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}
