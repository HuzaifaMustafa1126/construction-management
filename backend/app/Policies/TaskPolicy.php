<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\TaskComment;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user, ?Project $project = null): bool
    {
        return $user->can('tasks.view') && ($project === null || $user->can('view', $project));
    }

    public function create(User $user, Project $project): bool
    {
        return $user->can('tasks.create') && $user->can('update', $project);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->can('projects.edit') && $user->can('update', $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function assign(User $user, Project $project): bool
    {
        return $user->can('tasks.assign') && $user->can('update', $project);
    }

    public function toggleChecklist(User $user, Project $project): bool
    {
        return $this->update($user, $project) || $this->changeStatus($user, $project);
    }

    public function participate(User $user, Project $project): bool
    {
        return $this->update($user, $project)
            || $this->create($user, $project)
            || $this->changeStatus($user, $project);
    }

    public function manageComment(User $user, Project $project, TaskComment $comment): bool
    {
        return $this->update($user, $project)
            || ((int) $comment->user_id === (int) $user->id && $this->participate($user, $project));
    }

    public function changeStatus(User $user, Project $project): bool
    {
        return $user->can('tasks.complete') && $user->can('update', $project);
    }
}
