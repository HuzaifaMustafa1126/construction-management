<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $this->sameCompany($user, $project)
            && ($user->hasRole('company_admin') || $this->assigned($user, $project));
    }

    public function update(User $user, Project $project): bool
    {
        return $this->view($user, $project) && ! $user->hasRole('client');
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->sameCompany($user, $project) && $user->hasRole('company_admin');
    }

    public function manageTeam(User $user, Project $project): bool
    {
        return $this->sameCompany($user, $project) && $user->hasRole('company_admin');
    }

    public function viewBudget(User $user, Project $project): bool
    {
        return $this->view($user, $project) && $user->can('projects.budget.view');
    }

    private function sameCompany(User $user, Project $project): bool
    {
        return (int) $user->company_id === (int) $project->company_id;
    }

    private function assigned(User $user, Project $project): bool
    {
        return $project->members()->where('users.id', $user->id)->exists();
    }
}
