<?php

use App\Http\Controllers\Organizations\CheckoutController;
use App\Http\Controllers\Organizations\ConnectedAccountController;
use App\Http\Controllers\Organizations\FeatureController;
use App\Http\Controllers\Organizations\Inbox\ContactController;
use App\Http\Controllers\Organizations\Inbox\ConversationController;
use App\Http\Controllers\Organizations\Inbox\InboxController;
use App\Http\Controllers\Organizations\LicenseController;
use App\Http\Controllers\Organizations\SubscriptionController;
use App\Http\Controllers\Organizations\UsageController;
use App\Http\Controllers\Plans\PlanController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('plans', [PlanController::class, 'index'])
        ->name('plans.index');

    Route::get('plans/{plan}', [PlanController::class, 'show'])
        ->name('plans.show');

    Route::middleware('organization.member')->group(function (): void {
        Route::get('organizations/{organization:slug}/subscription', [SubscriptionController::class, 'show'])
            ->name('organizations.subscription.show');

        Route::post('organizations/{organization:slug}/subscription/trial', [SubscriptionController::class, 'startTrial'])
            ->name('organizations.subscription.trial');

        Route::post('organizations/{organization:slug}/subscription/cancel', [SubscriptionController::class, 'cancel'])
            ->name('organizations.subscription.cancel');

        Route::post('organizations/{organization:slug}/subscription/resume', [SubscriptionController::class, 'resume'])
            ->name('organizations.subscription.resume');

        Route::post('organizations/{organization:slug}/subscription/plan', [SubscriptionController::class, 'changePlan'])
            ->name('organizations.subscription.plan');

        Route::delete('organizations/{organization:slug}/subscription/plan', [SubscriptionController::class, 'cancelPlanChange'])
            ->name('organizations.subscription.plan.cancel');

        Route::post('organizations/{organization:slug}/subscription/suspend', [SubscriptionController::class, 'suspend'])
            ->name('organizations.subscription.suspend');

        Route::post('organizations/{organization:slug}/subscription/reinstate', [SubscriptionController::class, 'reinstate'])
            ->name('organizations.subscription.reinstate');

        Route::get('organizations/{organization:slug}/billing/history', [SubscriptionController::class, 'history'])
            ->name('organizations.billing.history');

        Route::post('organizations/{organization:slug}/checkout', [CheckoutController::class, 'store'])
            ->name('organizations.checkout.store');

        Route::get('organizations/{organization:slug}/checkout/{order}', [CheckoutController::class, 'show'])
            ->name('organizations.checkout.show');

        Route::post('organizations/{organization:slug}/checkout/{order}', [CheckoutController::class, 'confirm'])
            ->name('organizations.checkout.confirm');

        Route::get('organizations/{organization:slug}/licenses', [LicenseController::class, 'index'])
            ->name('organizations.licenses.index');

        Route::post('organizations/{organization:slug}/licenses', [LicenseController::class, 'store'])
            ->name('organizations.licenses.store');

        Route::post('organizations/{organization:slug}/licenses/{license}/rotate', [LicenseController::class, 'rotate'])
            ->name('organizations.licenses.rotate');

        Route::delete('organizations/{organization:slug}/licenses/{license}', [LicenseController::class, 'destroy'])
            ->name('organizations.licenses.destroy');

        Route::get('organizations/{organization:slug}/usage', [UsageController::class, 'index'])
            ->name('organizations.usage.index');

        Route::get('organizations/{organization:slug}/features/audit-log', [FeatureController::class, 'auditLog'])
            ->middleware('organization.entitlement:audit_log')
            ->name('organizations.features.audit-log');

        Route::get('organizations/{organization:slug}/connected-accounts', [ConnectedAccountController::class, 'index'])
            ->name('organizations.connected-accounts.index');

        Route::post('organizations/{organization:slug}/connected-accounts/connect', [ConnectedAccountController::class, 'connect'])
            ->name('organizations.connected-accounts.connect');

        Route::get('organizations/{organization:slug}/connected-accounts/callback', [ConnectedAccountController::class, 'callback'])
            ->name('organizations.connected-accounts.callback');

        Route::delete('organizations/{organization:slug}/connected-accounts/{connected_account}', [ConnectedAccountController::class, 'destroy'])
            ->name('organizations.connected-accounts.destroy');

        Route::get('organizations/{organization:slug}/inbox', [InboxController::class, 'index'])
            ->name('organizations.inbox.index');

        Route::get('organizations/{organization:slug}/inbox/conversations/{conversation}', [InboxController::class, 'show'])
            ->name('organizations.inbox.show');

        Route::post('organizations/{organization:slug}/inbox/conversations', [ConversationController::class, 'store'])
            ->name('organizations.inbox.conversations.store');

        Route::put('organizations/{organization:slug}/inbox/conversations/{conversation}', [ConversationController::class, 'update'])
            ->name('organizations.inbox.conversations.update');

        Route::post('organizations/{organization:slug}/inbox/conversations/{conversation}/assign', [ConversationController::class, 'assign'])
            ->name('organizations.inbox.conversations.assign');

        Route::post('organizations/{organization:slug}/inbox/conversations/{conversation}/mark-read', [ConversationController::class, 'markRead'])
            ->name('organizations.inbox.conversations.mark-read');

        Route::get('organizations/{organization:slug}/inbox/contacts', [ContactController::class, 'index'])
            ->name('organizations.inbox.contacts.index');

        Route::post('organizations/{organization:slug}/inbox/contacts', [ContactController::class, 'store'])
            ->name('organizations.inbox.contacts.store');

        Route::put('organizations/{organization:slug}/inbox/contacts/{contact}', [ContactController::class, 'update'])
            ->name('organizations.inbox.contacts.update');
    });
});
