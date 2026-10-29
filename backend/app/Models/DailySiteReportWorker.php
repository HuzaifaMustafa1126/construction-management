<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySiteReportWorker extends Model
{
    use BelongsToCompany;

    public const TYPES = ['mason', 'helper', 'electrician', 'plumber', 'carpenter', 'painter', 'steel_fixer', 'operator', 'other'];

    protected $fillable = ['worker_type', 'workers_present'];

    protected function casts(): array
    {
        return ['workers_present' => 'integer'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(DailySiteReport::class, 'daily_site_report_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
