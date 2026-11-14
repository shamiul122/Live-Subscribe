<?php

use App\Http\Controllers\Organizations\InvitationAcceptanceController;
use App\Http\Controllers\Organizations\OrganizationController;
use App\Http\Controllers\Organizations\OrganizationInvitationController;
use App\Http\Controllers\Organizations\OrganizationMemberController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('organizations', [OrganizationController::class, 'index'])
        ->name('organizations.index');

    Route::get('organizations/create', [OrganizationController::class, 'create'])
        ->name('organizations.create');

    Route::post('organizations', [OrganizationController::class, 'store'])
        ->name('organizations.store');

    Route::middleware('organization.member')->group(function (): void {
        Route::get('organizations/{organization:slug}', [OrganizationController::class, 'show'])
            ->name('organizations.show');

        Route::get('organizations/{organization:slug}/edit', [OrganizationController::class, 'edit'])
            ->name('organizations.edit');

        Route::patch('organizations/{organization:slug}', [OrganizationController::class, 'update'])
            ->name('organizations.update');

        Route::delete('organizations/{organization:slug}', [OrganizationController::class, 'destroy'])
            ->name('organizations.destroy');

        Route::post('organizations/{organization:slug}/switch', [OrganizationController::class, 'switch'])
            ->name('organizations.switch');

        Route::post('organizations/{organization:slug}/transfer-ownership', [OrganizationController::class, 'transferOwnership'])
            ->name('organizations.transfer-ownership');

        Route::delete('organizations/{organization:slug}/leave', [OrganizationController::class, 'leave'])
            ->name('organizations.leave');

        Route::post('organizations/{organization:slug}/invitations', [OrganizationInvitationController::class, 'store'])
            ->name('organizations.invitations.store');

        Route::delete('organizations/{organization:slug}/invitations/{invitation}', [OrganizationInvitationController::class, 'destroy'])
            ->name('organizations.invitations.destroy');

        Route::patch('organizations/{organization:slug}/members/{membership}', [OrganizationMemberController::class, 'update'])
            ->name('organizations.members.update');

        Route::delete('organizations/{organization:slug}/members/{membership}', [OrganizationMemberController::class, 'destroy'])
            ->name('organizations.members.destroy');
    });
});

Route::middleware('auth')->group(function (): void {
    Route::get('invitations/{token}', [InvitationAcceptanceController::class, 'show'])
        ->name('invitations.show');

    Route::post('invitations/{token}', [InvitationAcceptanceController::class, 'store'])
        ->name('invitations.accept');
});
