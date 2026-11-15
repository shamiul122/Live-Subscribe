<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\User;
use App\Policies\CampaignPolicy;
use App\Services\Campaigns\CampaignAudienceService;
use App\Services\Campaigns\CampaignRecipientService;
use App\Services\Campaigns\CampaignService;
use App\Services\Campaigns\CampaignStatisticsService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the organization-scoped marketing campaign foundation.
 *
 * Only one policy may be registered per model, so the campaign rules are
 * exposed as named gates on the organization, mirroring the inbox policy.
 */
class CampaignServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CampaignAudienceService::class);
        $this->app->singleton(CampaignRecipientService::class);
        $this->app->singleton(CampaignStatisticsService::class);
        $this->app->singleton(CampaignService::class);
    }

    public function boot(): void
    {
        $policy = new CampaignPolicy;

        Gate::define('campaign.view', fn (User $user, Organization $organization): bool => $policy->view($user, $organization));
        Gate::define('campaign.manage', fn (User $user, Organization $organization): bool => $policy->manage($user, $organization));
    }
}
