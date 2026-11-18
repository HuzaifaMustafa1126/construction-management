<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class MilestonePolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->can('projects.view') && $user->can('view', $project);
    }

    public function manage(User $user, Project $project): bool
    {
        return $user->can('projects.edit') && $user->can('update', $project);
    }
}
