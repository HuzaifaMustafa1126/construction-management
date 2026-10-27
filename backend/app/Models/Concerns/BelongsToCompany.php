<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            $builder->where(
                $builder->getModel()->qualifyColumn('company_id'),
                app(TenantContext::class)->companyId(),
            );
        });

        static::saving(function ($model): void {
            $companyId = app(TenantContext::class)->companyId();

            if (! $model->exists && $model->company_id === null) {
                $model->company_id = $companyId;
            }

            if ((int) $model->company_id !== $companyId) {
                throw new LogicException('Cross-company writes are not allowed.');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
