<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Milestone extends Model
{
    use BelongsToCompany;

    public const STATUSES = ['pending', 'in_progress', 'completed', 'delayed'];

    protected $attributes = ['status' => 'pending'];

    protected $fillable = ['phase_id', 'name', 'description', 'due_date', 'completed_date', 'status'];

    protected function casts(): array
    {
        return [
            'phase_id' => 'integer',
            'due_date' => 'date:Y-m-d',
            'completed_date' => 'date:Y-m-d',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(ProjectPhase::class, 'phase_id');
    }
}
