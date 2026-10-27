<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use Closure;
use LogicException;

class TenantContext
{
    private ?Company $company = null;

    public function establish(Company $company): void
    {
        if (! $company->exists || $company->trashed()) {
            throw new LogicException('A persisted company is required for tenant context.');
        }

        if ($company->status !== Company::STATUS_ACTIVE) {
            throw new LogicException('An active company is required for tenant context.');
        }

        if ($this->company && ! $this->company->is($company)) {
            throw new LogicException('Tenant context cannot be replaced without being cleared.');
        }

        $this->company = $company;
    }

    public function company(): Company
    {
        if (! $this->company) {
            throw new LogicException('Tenant context has not been established.');
        }

        return $this->company;
    }

    public function companyId(): int
    {
        return (int) $this->company()->getKey();
    }

    public function hasCompany(): bool
    {
        return $this->company !== null;
    }

    public function clear(): void
    {
        $this->company = null;
    }

    /**
     * Run trusted CLI or queued work in an explicit tenant context.
     */
    public function run(Company $company, Closure $callback): mixed
    {
        $previous = $this->company;
        $this->company = null;

        try {
            $this->establish($company);

            return $callback($this);
        } finally {
            $this->company = $previous;
        }
    }
}
