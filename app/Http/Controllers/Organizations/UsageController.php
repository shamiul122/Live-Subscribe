<?php

namespace App\Http\Controllers\Organizations;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Billing\BillingPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UsageController extends Controller
{
    public function __construct(private readonly BillingPresenter $presenter)
    {
    }

    public function index(Request $request, Organization $organization): Response
    {
        Gate::authorize('billing.viewUsage', $organization);

        $subscription = $organization->currentSubscription()->first();

        return Inertia::render('organizations/usage/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'subscription' => $subscription === null ? null : $this->presenter->subscriptionSummary($subscription),
            'features' => $subscription === null ? [] : $this->presenter->featureLines($organization, $subscription),
        ]);
    }
}
