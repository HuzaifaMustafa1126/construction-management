<?php

namespace App\Policies;

use App\Models\MaterialStockAdjustment;
use App\Models\Project;
use App\Models\User;

class MaterialStockAdjustmentPolicy
{
    public function create(User $user, Project $project): bool
    {
        return $this->access($user, $project);
    }

    public function view(User $user, MaterialStockAdjustment $adjustment): bool
    {
        return $this->access($user, $adjustment->project);
    }

    public function update(User $user, MaterialStockAdjustment $adjustment): bool
    {
        return $this->view($user, $adjustment) && $adjustment->status === 'draft';
    }

    public function post(User $user, MaterialStockAdjustment $adjustment): bool
    {
        return $this->view($user, $adjustment);
    }

    public function cancel(User $user, MaterialStockAdjustment $adjustment): bool
    {
        return $this->view($user, $adjustment);
    }

    private function access(User $user, Project $project): bool
    {
        return ! $user->hasRole('client') && (int) $user->company_id === (int) $project->company_id && ($user->hasRole('company_admin') || $project->members()->where('users.id', $user->id)->exists());
    }
}
