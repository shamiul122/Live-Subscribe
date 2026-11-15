<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\User;
use App\Policies\MessageTemplatePolicy;
use App\Services\Templates\MessageTemplateService;
use App\Services\Templates\TemplateRendererService;
use App\Services\Templates\TemplateVariableService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the organization-scoped message template foundation.
 *
 * Only one policy may be registered per model, so the template rules are
 * exposed as named gates on the organization, mirroring the campaign policy.
 */
class TemplateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TemplateVariableService::class);
        $this->app->singleton(TemplateRendererService::class);
        $this->app->singleton(MessageTemplateService::class);
    }

    public function boot(): void
    {
        $policy = new MessageTemplatePolicy;

        Gate::define('message_template.view', fn (User $user, Organization $organization): bool => $policy->view($user, $organization));
        Gate::define('message_template.manage', fn (User $user, Organization $organization): bool => $policy->manage($user, $organization));
    }
}
