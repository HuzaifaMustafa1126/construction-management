<?php

namespace App\Providers;

use App\Models\DailySiteReport;
use App\Models\MaterialDelivery;
use App\Models\Project;
use App\Models\ProjectInventory;
use App\Models\SitePhoto;
use App\Models\Supplier;
use App\Policies\DailySiteReportPolicy;
use App\Policies\MaterialDeliveryPolicy;
use App\Policies\ProjectInventoryPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SitePhotoPolicy;
use App\Policies\SupplierPolicy;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn () => new TenantContext);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(ProjectInventory::class, ProjectInventoryPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(MaterialDelivery::class, MaterialDeliveryPolicy::class);
        Gate::policy(DailySiteReport::class, DailySiteReportPolicy::class);
        Gate::policy(SitePhoto::class, SitePhotoPolicy::class);
    }
}
