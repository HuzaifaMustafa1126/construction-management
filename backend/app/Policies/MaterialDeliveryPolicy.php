<?php

namespace App\Policies;

use App\Models\MaterialDelivery;
use App\Models\Project;
use App\Models\User;

class MaterialDeliveryPolicy
{
    public function create(User $u, Project $p): bool
    {
        return $this->access($u, $p);
    }

    public function view(User $u, MaterialDelivery $d): bool
    {
        return $this->access($u, $d->project);
    }

    public function update(User $u, MaterialDelivery $d): bool
    {
        return $this->view($u, $d) && $d->status === 'draft';
    }

    public function receive(User $u, MaterialDelivery $d): bool
    {
        return $this->view($u, $d);
    }

    public function cancel(User $u, MaterialDelivery $d): bool
    {
        return $this->view($u, $d);
    }

    private function access(User $u, Project $p): bool
    {
        return ! $u->hasRole('client') && (int) $u->company_id === (int) $p->company_id && ($u->hasRole('company_admin') || $p->members()->where('users.id', $u->id)->exists());
    }
}
