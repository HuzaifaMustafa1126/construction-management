<?php

namespace App\Providers;

use App\Models\DailySiteReport;
use App\Models\MaterialConsumption;
use App\Models\MaterialDelivery;
use App\Models\MaterialStockAdjustment;
use App\Models\Project;
use App\Models\ProjectInventory;
use App\Models\SitePhoto;
use App\Models\Supplier;
use App\Policies\DailySiteReportPolicy;
use App\Policies\MaterialConsumptionPolicy;
use App\Policies\MaterialDeliveryPolicy;
use App\Policies\MaterialStockAdjustmentPolicy;
use App\Policies\ProjectInventoryPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SitePhotoPolicy;
use App\Policies\SupplierPolicy;
use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('company-registration', fn ($request) => Limit::perHour(5)->by('company-registration:'.$request->ip()));
        RateLimiter::for('company-verification', fn ($request) => Limit::perMinute(10)->by('company-verification:'.$request->ip()));
        RateLimiter::for('company-verification-resend', fn ($request) => Limit::perMinute(3)->by('company-verification-resend:'.$request->ip()));

        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(ProjectInventory::class, ProjectInventoryPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(MaterialDelivery::class, MaterialDeliveryPolicy::class);
        Gate::policy(MaterialConsumption::class, MaterialConsumptionPolicy::class);
        Gate::policy(MaterialStockAdjustment::class, MaterialStockAdjustmentPolicy::class);
        Gate::policy(DailySiteReport::class, DailySiteReportPolicy::class);
        Gate::policy(SitePhoto::class, SitePhotoPolicy::class);
    }
}
