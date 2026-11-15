<?php

namespace App\Services\Campaigns;

use App\Enums\CampaignAudienceType;
use App\Models\Campaign;
use App\Models\CampaignAudienceSnapshot;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\User;
use App\Services\Inbox\ContactService;
use Illuminate\Validation\ValidationException;

/**
 * Resolves and freezes the contacts a campaign intends to reach.
 *
 * Every query is organization scoped. A contact id that belongs to another
 * organization is rejected instead of silently dropped, so a crafted request
 * can never widen an audience.
 */
final class CampaignAudienceService
{
    public function __construct(private readonly ContactService $contacts) {}

    /**
     * Resolve the audience configuration into a de-duplicated list of contact
     * ids that all belong to the organization.
     *
     * @param  array<string, mixed>  $config
     * @return array<int, int>
     */
    public function resolveContactIds(Organization $organization, CampaignAudienceType $type, array $config): array
    {
        return match ($type) {
            CampaignAudienceType::All => $this->allContactIds($organization),
            CampaignAudienceType::Selected => $this->selectedContactIds($organization, $config),
        };
    }

    /**
     * Normalise a submitted configuration so storage is deterministic.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function normalizeConfig(CampaignAudienceType $type, array $config): array
    {
        return match ($type) {
            CampaignAudienceType::All => [],
            CampaignAudienceType::Selected => [
                'contact_ids' => array_values(array_unique(array_map(
                    static fn (mixed $id): int => (int) $id,
                    is_array($config['contact_ids'] ?? null) ? $config['contact_ids'] : [],
                ))),
            ],
        };
    }

    /**
     * Estimated audience size before execution.
     *
     * @param  array<string, mixed>  $config
     */
    public function estimate(Organization $organization, CampaignAudienceType $type, array $config): int
    {
        return count($this->resolveContactIds($organization, $type, $config));
    }

    /**
     * Build or refresh the campaign's frozen audience snapshot.
     *
     * The resolved contact ids are stored on the snapshot, so a later contact
     * edit, tag change, or soft delete does not silently change the audience of
     * an existing campaign.
     */
    public function snapshot(Organization $organization, Campaign $campaign, ?User $actor = null): CampaignAudienceSnapshot
    {
        $type = $campaign->audience_type;
        $config = $campaign->audience_config ?? [];
        $contactIds = $this->resolveContactIds($organization, $type, $config);

        $snapshot = CampaignAudienceSnapshot::query()->updateOrCreate(
            ['campaign_id' => $campaign->id],
            [
                'organization_id' => $organization->id,
                'audience_type' => $type,
                'filters' => $config,
                'contact_ids' => $contactIds,
                'contact_count' => count($contactIds),
                'captured_by_id' => $actor?->id,
                'captured_at' => now(),
            ],
        );

        $campaign->forceFill(['audience_size' => count($contactIds)])->save();

        return $snapshot;
    }

    /**
     * Preview an audience without persisting anything.
     *
     * @param  array<string, mixed>  $config
     * @return array{audience_type: string, count: int, contact_ids: array<int, int>}
     */
    public function preview(Organization $organization, CampaignAudienceType $type, array $config): array
    {
        $contactIds = $this->resolveContactIds($organization, $type, $config);

        return [
            'audience_type' => $type->value,
            'count' => count($contactIds),
            'contact_ids' => $contactIds,
        ];
    }

    /**
     * Organization-scoped contacts available for manual selection.
     *
     * @return array<int, array<string, mixed>>
     */
    public function availableContacts(Organization $organization, ?string $search = null): array
    {
        return $this->contacts->list($organization, $search);
    }

    /**
     * @return array<int, int>
     */
    private function allContactIds(Organization $organization): array
    {
        return Contact::query()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, int>
     */
    private function selectedContactIds(Organization $organization, array $config): array
    {
        $raw = $config['contact_ids'] ?? null;

        if (! is_array($raw)) {
            throw ValidationException::withMessages([
                'audience_config.contact_ids' => 'The selected contacts must be an array of contact identifiers.',
            ]);
        }

        $requested = array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            $raw,
        )));

        $requested = array_values(array_filter($requested, static fn (int $id): bool => $id > 0));

        if ($requested === []) {
            throw ValidationException::withMessages([
                'audience_config.contact_ids' => 'Select at least one contact for a selected audience.',
            ]);
        }

        $found = Contact::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $requested)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $missing = array_diff($requested, $found);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'audience_config.contact_ids' => 'One or more selected contacts do not belong to this organization.',
            ]);
        }

        sort($found);

        return $found;
    }
}
