<?php

namespace App\Http\Controllers\Organizations;

use App\Actions\Billing\ConfirmOrderPayment;
use App\Actions\Billing\PlaceOrder;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\ConfirmCheckoutRequest;
use App\Http\Requests\Billing\StartCheckoutRequest;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Plan;
use App\Services\Billing\BillingPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(private readonly BillingPresenter $presenter)
    {
    }

    public function store(
        StartCheckoutRequest $request,
        Organization $organization,
        PlaceOrder $placeOrder,
    ): RedirectResponse {
        $plan = Plan::query()->where('slug', $request->validated('plan'))->firstOrFail();

        $order = $placeOrder->handle(
            organization: $organization,
            plan: $plan,
            actor: $request->user(),
            type: PlaceOrder::TYPE_NEW,
            idempotencyKey: $request->validated('idempotency_key'),
        );

        return redirect()->route('organizations.checkout.show', [
            'organization' => $organization,
            'order' => $order,
        ]);
    }

    public function show(Request $request, Organization $organization, Order $order): Response|RedirectResponse
    {
        Gate::authorize('billing.manageBilling', $organization);

        abort_unless($order->organization_id === $organization->id, 404);

        if (! $order->isPayable()) {
            return redirect()->route('organizations.subscription.show', $organization);
        }

        $order->load(['plan', 'transactions']);

        return Inertia::render('organizations/subscription/Checkout', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'order' => $this->presenter->orderSummary($order),
            'plan' => $order->plan === null ? null : $this->presenter->planSummary($order->plan),
            'gateway' => (string) config('billing.gateway', 'mock'),
            'mock_payment' => (string) config('billing.gateway', 'mock') === 'mock',
            'mock_enabled' => (bool) config('billing.mock.enabled', false),
        ]);
    }

    public function confirm(
        ConfirmCheckoutRequest $request,
        Organization $organization,
        Order $order,
        ConfirmOrderPayment $confirm,
    ): RedirectResponse {
        Gate::authorize('billing.manageBilling', $organization);

        abort_unless($order->organization_id === $organization->id, 404);

        $transaction = $confirm->handle($order, $request->user(), $request->validated('outcome'));

        if ($transaction->status === PaymentStatus::Succeeded) {
            return redirect()
                ->route('organizations.subscription.show', $organization)
                ->with('status', 'Payment confirmed. Your subscription is active.');
        }

        return redirect()
            ->route('organizations.checkout.show', ['organization' => $organization, 'order' => $order])
            ->withErrors([
                'payment' => $transaction->failure_message ?? 'The payment was not completed. You can try again.',
            ]);
    }
}
