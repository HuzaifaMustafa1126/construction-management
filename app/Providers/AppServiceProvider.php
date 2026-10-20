<?php

namespace App\Providers;

use App\Models\DailySiteReport;
use App\Models\Project;
use App\Models\SitePhoto;
use App\Policies\DailySiteReportPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SitePhotoPolicy;
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
        Gate::policy(DailySiteReport::class, DailySiteReportPolicy::class);
        Gate::policy(SitePhoto::class, SitePhotoPolicy::class);
    }
}
