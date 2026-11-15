<?php

use App\Http\Controllers\Organizations\Templates\MessageTemplateController;
use App\Http\Controllers\Organizations\Templates\MessageTemplatePreviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::middleware('organization.member')->group(function (): void {
        Route::get('organizations/{organization:slug}/message-templates', [MessageTemplateController::class, 'index'])
            ->name('organizations.message-templates.index');

        Route::get('organizations/{organization:slug}/message-templates/create', [MessageTemplateController::class, 'create'])
            ->name('organizations.message-templates.create');

        Route::post('organizations/{organization:slug}/message-templates', [MessageTemplateController::class, 'store'])
            ->name('organizations.message-templates.store');

        Route::post('organizations/{organization:slug}/message-templates/preview', [MessageTemplatePreviewController::class, 'render'])
            ->name('organizations.message-templates.preview.render');

        Route::get('organizations/{organization:slug}/message-templates/{message_template}', [MessageTemplateController::class, 'show'])
            ->name('organizations.message-templates.show');

        Route::get('organizations/{organization:slug}/message-templates/{message_template}/edit', [MessageTemplateController::class, 'edit'])
            ->name('organizations.message-templates.edit');

        Route::put('organizations/{organization:slug}/message-templates/{message_template}', [MessageTemplateController::class, 'update'])
            ->name('organizations.message-templates.update');

        Route::delete('organizations/{organization:slug}/message-templates/{message_template}', [MessageTemplateController::class, 'destroy'])
            ->name('organizations.message-templates.destroy');

        Route::post('organizations/{organization:slug}/message-templates/{message_template}/archive', [MessageTemplateController::class, 'archive'])
            ->name('organizations.message-templates.archive');

        Route::post('organizations/{organization:slug}/message-templates/{message_template}/restore', [MessageTemplateController::class, 'restore'])
            ->name('organizations.message-templates.restore');

        Route::get('organizations/{organization:slug}/message-templates/{message_template}/preview', [MessageTemplatePreviewController::class, 'show'])
            ->name('organizations.message-templates.preview.show');
    });
});
