<?php

namespace App\Policies;

use App\Models\MaterialConsumption;
use App\Models\Project;
use App\Models\User;

class MaterialConsumptionPolicy
{
    public function create(User $user, Project $project): bool
    {
        return $this->access($user, $project);
    }

    public function view(User $user, MaterialConsumption $consumption): bool
    {
        return $this->access($user, $consumption->project);
    }

    public function update(User $user, MaterialConsumption $consumption): bool
    {
        return $this->view($user, $consumption) && $consumption->status === 'draft';
    }

    public function post(User $user, MaterialConsumption $consumption): bool
    {
        return $this->view($user, $consumption);
    }

    public function cancel(User $user, MaterialConsumption $consumption): bool
    {
        return $this->view($user, $consumption);
    }

    private function access(User $user, Project $project): bool
    {
        return ! $user->hasRole('client') && (int) $user->company_id === (int) $project->company_id
            && ($user->hasRole('company_admin') || $project->members()->where('users.id', $user->id)->exists());
    }
}
