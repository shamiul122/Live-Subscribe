<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\User;
use App\Policies\InboxPolicy;
use App\Policies\OrganizationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\Communication\CommunicationProviderRegistry::class, function () {
            $registry = new \App\Services\Communication\CommunicationProviderRegistry();
            $registry->registerDefaults();
            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Organization::class, OrganizationPolicy::class);

        Gate::define('inbox.view', fn (User $user, Organization $organization): bool => (new InboxPolicy)->view($user, $organization));
        Gate::define('inbox.manage', fn (User $user, Organization $organization): bool => (new InboxPolicy)->manage($user, $organization));
    }
}
