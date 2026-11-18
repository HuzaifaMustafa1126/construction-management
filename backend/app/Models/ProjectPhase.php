<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ProjectPhase extends Model
{
    use BelongsToCompany;

    public const STATUSES = ['pending', 'active', 'completed', 'delayed'];

    protected $fillable = ['name', 'description', 'start_date', 'end_date', 'status'];

    protected static function booted(): void
    {
        static::creating(function (ProjectPhase $phase): void {
            if (! $phase->project()->where('projects.company_id', $phase->company_id)->exists()) {
                throw new LogicException('A phase requires a project in its company.');
            }
        });

        static::updating(function (ProjectPhase $phase): void {
            if ($phase->isDirty('project_id')) {
                throw new LogicException('Phase project ownership cannot be changed.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'progress_percentage' => 'decimal:2',
        ];
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class, 'phase_id')->orderBy('due_date')->orderBy('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'phase_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
