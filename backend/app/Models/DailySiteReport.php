<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\DailySiteReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailySiteReport extends Model
{
    /** @use HasFactory<DailySiteReportFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'submitted', 'approved', 'changes_required'];

    protected $fillable = [
        'report_date', 'title', 'work_completed', 'work_in_progress', 'issues', 'delays',
        'delay_reason', 'weather_conditions', 'general_notes', 'progress_percentage',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date:Y-m-d', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime',
            'is_client_visible' => 'boolean', 'total_workers_present' => 'integer',
            'progress_percentage' => 'integer',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function workers(): HasMany
    {
        return $this->hasMany(DailySiteReportWorker::class);
    }

    public function sitePhotos(): HasMany
    {
        return $this->hasMany(SitePhoto::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}
