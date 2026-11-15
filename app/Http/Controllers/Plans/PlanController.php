<?php

namespace App\Http\Controllers\Plans;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\Billing\BillingPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function __construct(private readonly BillingPresenter $presenter)
    {
    }

    public function index(Request $request): Response
    {
        $plans = Plan::query()
            ->purchasable()
            ->with('features')
            ->orderBy('sort_order')
            ->orderBy('price_minor')
            ->get()
            ->map(fn (Plan $plan): array => $this->presenter->planSummary($plan))
            ->values();

        return Inertia::render('plans/Index', [
            'plans' => $plans,
        ]);
    }

    public function show(Request $request, Plan $plan): Response
    {
        $plan->load('features');

        return Inertia::render('plans/Show', [
            'plan' => $this->presenter->planSummary($plan),
        ]);
    }
}
