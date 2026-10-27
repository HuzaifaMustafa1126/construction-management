<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\SitePhoto;
use App\Models\User;

class SitePhotoPolicy
{
    public function create(User $user, Project $project): bool
    {
        return $this->projectAccess($user, $project) && ! $user->hasRole('client');
    }

    public function view(User $user, SitePhoto $photo): bool
    {
        if (! $this->projectAccess($user, $photo->project)) {
            return false;
        }

        return ! $user->hasRole('client')
            || ($photo->approval_status === 'approved' && $photo->is_client_visible);
    }

    public function update(User $user, SitePhoto $photo): bool
    {
        if (! $this->projectAccess($user, $photo->project) || $user->hasRole('client')) {
            return false;
        }

        return $user->hasRole('company_admin') || (
            (int) $photo->uploaded_by === (int) $user->id
            && in_array($photo->approval_status, ['pending', 'rejected'], true)
        );
    }

    public function review(User $user, SitePhoto $photo): bool
    {
        return $this->projectAccess($user, $photo->project) && $user->hasRole('company_admin');
    }

    public function visibility(User $user, SitePhoto $photo): bool
    {
        return $this->review($user, $photo);
    }

    public function delete(User $user, SitePhoto $photo): bool
    {
        return $this->review($user, $photo);
    }

    private function projectAccess(User $user, Project $project): bool
    {
        return (int) $user->company_id === (int) $project->company_id
            && ($user->hasRole('company_admin') || $project->members()->where('users.id', $user->id)->exists());
    }
}
