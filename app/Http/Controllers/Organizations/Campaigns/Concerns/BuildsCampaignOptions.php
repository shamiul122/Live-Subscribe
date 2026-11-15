<?php

namespace App\Http\Controllers\Organizations\Campaigns\Concerns;

use App\Enums\CampaignAudienceType;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\SocialProvider;
use App\Models\ConnectedAccount;
use App\Models\Organization;

/**
 * Shared option lists for the campaign screens.
 *
 * Options are always derived from the enums, never from free-form strings, so
 * the frontend and the validation rules can never drift apart.
 */
trait BuildsCampaignOptions
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function statusOptions(): array
    {
        return array_map(
            static fn (CampaignStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            CampaignStatus::cases(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function typeOptions(): array
    {
        return array_map(
            static fn (CampaignType $type): array => ['value' => $type->value, 'label' => $type->label()],
            CampaignType::cases(),
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
     * @return array<int, array{value: string, label: string}>
     */
    protected function audienceTypeOptions(): array
    {
        return array_map(
            static fn (CampaignAudienceType $type): array => ['value' => $type->value, 'label' => $type->label()],
            CampaignAudienceType::cases(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function recipientStatusOptions(): array
    {
        return array_map(
            static fn (CampaignRecipientStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            CampaignRecipientStatus::cases(),
        );
    }

    /**
     * Connected accounts of the organization, with a display summary.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function accountOptions(Organization $organization): array
    {
        return ConnectedAccount::query()
            ->where('organization_id', $organization->id)
            ->orderBy('account_name')
            ->get()
            ->map(static fn (ConnectedAccount $account): array => [
                'id' => $account->id,
                'provider' => $account->provider->value,
                'provider_label' => $account->provider->label(),
                'account_name' => $account->account_name,
                'account_username' => $account->account_username,
                'status' => $account->status->value,
                'status_label' => $account->status->label(),
                'is_active' => $account->isActive(),
            ])
            ->values()
            ->all();
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
