<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function view(User $u, Supplier $s): bool
    {
        return (int) $u->company_id === (int) $s->company_id && ! $u->hasRole('client');
    }

    public function manage(User $u, Supplier $s): bool
    {
        return $this->view($u, $s) && ! $u->hasRole('site_supervisor');
    }
}
