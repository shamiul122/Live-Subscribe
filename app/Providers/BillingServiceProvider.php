<?php

namespace App\Providers;

use App\Models\License;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;
use App\Observers\OrganizationObserver;
use App\Policies\BillingPolicy;
use App\Policies\LicensePolicy;
use App\Policies\OrderPolicy;
use App\Policies\SubscriptionPolicy;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\PaymentGatewayManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EntitlementService::class);
        $this->app->singleton(PaymentGatewayManager::class);
    }

    public function boot(): void
    {
        Organization::observe(OrganizationObserver::class);

        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(License::class, LicensePolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);

        $this->registerBillingGates();
    }

    /**
     * Expose the organization-scoped billing matrix as named gates. Only one
     * policy may be registered per model, so BillingPolicy rules are surfaced
     * here rather than replacing OrganizationPolicy.
     */
    private function registerBillingGates(): void
    {
        $policy = new BillingPolicy();

        Gate::define('billing.viewSubscription', fn (User $user, Organization $organization): bool => $policy->viewSubscription($user, $organization));
        Gate::define('billing.viewUsage', fn (User $user, Organization $organization): bool => $policy->viewUsage($user, $organization));
        Gate::define('billing.viewLicenses', fn (User $user, Organization $organization): bool => $policy->viewLicenses($user, $organization));
        Gate::define('billing.viewHistory', fn (User $user, Organization $organization): bool => $policy->viewHistory($user, $organization));
        Gate::define('billing.manageLicenses', fn (User $user, Organization $organization): bool => $policy->manageLicenses($user, $organization));
        Gate::define('billing.manageBilling', fn (User $user, Organization $organization): bool => $policy->manageBilling($user, $organization));
        Gate::define('billing.viewSocialAccounts', fn (User $user, Organization $organization): bool => $policy->viewSocialAccounts($user, $organization));
        Gate::define('billing.manageSocialAccounts', fn (User $user, Organization $organization): bool => $policy->manageSocialAccounts($user, $organization));
    }
}
