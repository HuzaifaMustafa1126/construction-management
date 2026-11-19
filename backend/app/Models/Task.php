<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class Task extends Model
{
    use BelongsToCompany, SoftDeletes;

    public const STATUSES = ['todo', 'in_progress', 'blocked', 'completed'];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    protected $attributes = ['status' => 'todo', 'priority' => 'medium', 'progress_percentage' => 0];

    protected $fillable = [
        'phase_id', 'milestone_id', 'title', 'description', 'assigned_to', 'start_date',
        'due_date', 'priority', 'status', 'position', 'progress_percentage', 'estimated_hours', 'actual_hours',
    ];

    protected static function booted(): void
    {
        static::creating(function (Task $task): void {
            if (! $task->project()->where('projects.company_id', $task->company_id)->exists()) {
                throw new LogicException('A task requires a project in its company.');
            }
        });

        static::updating(function (Task $task): void {
            if ($task->isDirty(['project_id', 'created_by'])) {
                throw new LogicException('Task project ownership and creator cannot be changed.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'phase_id' => 'integer',
            'milestone_id' => 'integer',
            'assigned_to' => 'integer',
            'position' => 'integer',
            'start_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
            'progress_percentage' => 'decimal:2',
            'estimated_hours' => 'decimal:2',
            'actual_hours' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(ProjectPhase::class, 'phase_id');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class)->orderBy('position')->orderBy('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at')->orderBy('id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('project', fn (Builder $projects) => $projects->accessibleTo($user));
    }
}
