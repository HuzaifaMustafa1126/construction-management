<?php

namespace App\Policies;

use App\Models\DailySiteReport;
use App\Models\Project;
use App\Models\User;

class DailySiteReportPolicy
{
    public function create(User $user, Project $project): bool
    {
        return $this->projectAccess($user, $project) && ! $user->hasRole('client');
    }

    public function view(User $user, DailySiteReport $report): bool
    {
        if (! $this->projectAccess($user, $report->project)) {
            return false;
        }
        if ($user->hasRole('company_admin')) {
            return true;
        }
        if ($user->hasRole('client')) {
            return $report->status === 'approved' && $report->is_client_visible;
        }

        return true;
    }

    public function update(User $user, DailySiteReport $report): bool
    {
        if (! $this->projectAccess($user, $report->project)
            || ! in_array($report->status, ['draft', 'changes_required'], true)) {
            return false;
        }
        if ($user->hasRole('company_admin')) {
            return true;
        }

        return ! $user->hasRole('client') && (int) $report->created_by === (int) $user->id;
    }

    public function submit(User $user, DailySiteReport $report): bool
    {
        if (! $this->projectAccess($user, $report->project)) {
            return false;
        }

        return $user->hasRole('company_admin') || (int) $report->created_by === (int) $user->id;
    }

    public function review(User $user, DailySiteReport $report): bool
    {
        return $this->projectAccess($user, $report->project)
            && ! $user->hasRole('client')
            && (int) $report->created_by !== (int) $user->id;
    }

    public function visibility(User $user, DailySiteReport $report): bool
    {
        return $this->sameCompany($user, $report) && $user->hasRole('company_admin');
    }

    public function delete(User $user, DailySiteReport $report): bool
    {
        return $this->sameCompany($user, $report) && $user->hasRole('company_admin');
    }

    private function projectAccess(User $user, Project $project): bool
    {
        return (int) $user->company_id === (int) $project->company_id
            && ($user->hasRole('company_admin') || $project->members()->where('users.id', $user->id)->exists());
    }

    private function sameCompany(User $user, DailySiteReport $report): bool
    {
        return (int) $user->company_id === (int) $report->company_id;
    }
}
