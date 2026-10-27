<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectInventory;
use App\Models\User;

class ProjectInventoryPolicy
{
    public function create(User $user, Project $project): bool
    {
        return $this->projectAccess($user, $project) && ! $user->hasRole('client');
    }

    public function view(User $user, ProjectInventory $inventory): bool
    {
        return ! $user->hasRole('client') && $this->projectAccess($user, $inventory->project);
    }

    public function update(User $user, ProjectInventory $inventory): bool
    {
        return $this->view($user, $inventory);
    }

    public function openingStock(User $user, ProjectInventory $inventory): bool
    {
        return $this->view($user, $inventory);
    }

    private function projectAccess(User $user, Project $project): bool
    {
        return (int) $user->company_id === (int) $project->company_id && ($user->hasRole('company_admin') || $project->members()->where('users.id', $user->id)->exists());
    }
}
