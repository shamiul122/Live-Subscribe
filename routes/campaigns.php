<?php

use App\Http\Controllers\Organizations\Campaigns\CampaignAudienceController;
use App\Http\Controllers\Organizations\Campaigns\CampaignController;
use App\Http\Controllers\Organizations\Campaigns\CampaignLifecycleController;
use App\Http\Controllers\Organizations\Campaigns\CampaignRecipientController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::middleware('organization.member')->group(function (): void {
        Route::get('organizations/{organization:slug}/campaigns', [CampaignController::class, 'index'])
            ->name('organizations.campaigns.index');

        Route::get('organizations/{organization:slug}/campaigns/create', [CampaignController::class, 'create'])
            ->name('organizations.campaigns.create');

        Route::post('organizations/{organization:slug}/campaigns', [CampaignController::class, 'store'])
            ->name('organizations.campaigns.store');

        Route::post('organizations/{organization:slug}/campaigns/audience/preview', [CampaignAudienceController::class, 'preview'])
            ->name('organizations.campaigns.audience.preview');

        Route::get('organizations/{organization:slug}/campaigns/{campaign}', [CampaignController::class, 'show'])
            ->name('organizations.campaigns.show');

        Route::get('organizations/{organization:slug}/campaigns/{campaign}/edit', [CampaignController::class, 'edit'])
            ->name('organizations.campaigns.edit');

        Route::put('organizations/{organization:slug}/campaigns/{campaign}', [CampaignController::class, 'update'])
            ->name('organizations.campaigns.update');

        Route::delete('organizations/{organization:slug}/campaigns/{campaign}', [CampaignController::class, 'destroy'])
            ->name('organizations.campaigns.destroy');

        Route::get('organizations/{organization:slug}/campaigns/{campaign}/recipients', [CampaignRecipientController::class, 'index'])
            ->name('organizations.campaigns.recipients.index');

        Route::post('organizations/{organization:slug}/campaigns/{campaign}/recipients/prepare', [CampaignLifecycleController::class, 'prepare'])
            ->name('organizations.campaigns.recipients.prepare');

        Route::post('organizations/{organization:slug}/campaigns/{campaign}/ready', [CampaignLifecycleController::class, 'ready'])
            ->name('organizations.campaigns.ready');

        Route::post('organizations/{organization:slug}/campaigns/{campaign}/pause', [CampaignLifecycleController::class, 'pause'])
            ->name('organizations.campaigns.pause');

        Route::post('organizations/{organization:slug}/campaigns/{campaign}/resume', [CampaignLifecycleController::class, 'resume'])
            ->name('organizations.campaigns.resume');

        Route::post('organizations/{organization:slug}/campaigns/{campaign}/cancel', [CampaignLifecycleController::class, 'cancel'])
            ->name('organizations.campaigns.cancel');
    });
});
