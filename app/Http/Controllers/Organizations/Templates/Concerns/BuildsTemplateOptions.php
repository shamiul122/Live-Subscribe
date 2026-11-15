<?php

namespace App\Http\Controllers\Organizations\Templates\Concerns;

use App\Enums\MessageTemplateCategory;
use App\Enums\MessageTemplateStatus;
use App\Enums\SocialProvider;
use App\Models\Organization;
use App\Services\Templates\TemplateVariableService;

/**
 * Shared option lists for the message template screens.
 *
 * Options are always derived from the enums, never from free-form strings, so
 * the frontend and the validation rules can never drift apart.
 */
trait BuildsTemplateOptions
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function statusOptions(): array
    {
        return array_map(
            static fn (MessageTemplateStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            MessageTemplateStatus::cases(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function categoryOptions(): array
    {
        return array_map(
            static fn (MessageTemplateCategory $category): array => ['value' => $category->value, 'label' => $category->label()],
            MessageTemplateCategory::cases(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function channelOptions(): array
    {
        return array_map(
            static fn (SocialProvider $provider): array => ['value' => $provider->value, 'label' => $provider->label()],
            SocialProvider::cases(),
        );
    }

    /**
     * @return array<int, array{key: string, label: string, sample: string, description: string}>
     */
    protected function variableOptions(): array
    {
        return app(TemplateVariableService::class)->supported();
    }

    /**
     * @return array{id: int, name: string, slug: string}
     */
    protected function organizationSummary(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
        ];
    }
}
