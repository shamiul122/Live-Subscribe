<?php

namespace App\Services\Campaigns;

use App\Enums\CampaignAudienceType;
use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Models\Campaign;
use App\Models\CampaignAudienceSnapshot;
use App\Models\ConnectedAccount;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Organization-scoped marketing campaign management.
 *
 * The service owns campaign creation, editing, and lifecycle transitions. It
 * never sends a message and never marks a recipient as delivered: that boundary
 * belongs to a future delivery integration.
 */
final class CampaignService
{
    public function __construct(
        private readonly CampaignAudienceService $audience,
        private readonly CampaignRecipientService $recipients,
        private readonly CampaignStatisticsService $statistics,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(
        Organization $organization,
        ?string $search = null,
        ?CampaignStatus $status = null,
        ?CampaignType $type = null,
        ?string $channel = null,
    ): array {
        $campaigns = Campaign::query()
            ->where('organization_id', $organization->id)
            ->with('connectedAccount')
            ->withCount('recipients')
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->when($type !== null, fn ($query) => $query->where('type', $type->value))
            ->when($channel !== null && $channel !== '', fn ($query) => $query->where('channel', $channel))
            ->when($search !== null && $search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return $campaigns
            ->map(fn (Campaign $campaign): array => $this->toArray($campaign))
            ->values()
            ->all();
    }

    public function find(Organization $organization, int $campaignId): Campaign
    {
        $campaign = Campaign::query()
            ->where('organization_id', $organization->id)
            ->with(['connectedAccount', 'audienceSnapshot', 'createdBy'])
            ->withCount('recipients')
            ->find($campaignId);

        if ($campaign === null) {
            throw new ModelNotFoundException("Campaign [{$campaignId}] was not found for this organization.");
        }

        return $campaign;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, Organization $organization, array $data): Campaign
    {
        return DB::transaction(function () use ($actor, $organization, $data): Campaign {
            $campaign = Campaign::query()->create([
                'organization_id' => $organization->id,
                'created_by_id' => $actor->id,
                'status' => CampaignStatus::Draft,
                ...$this->attributes($organization, $data),
            ]);

            $this->audience->snapshot($organization, $campaign, $actor);

            return $campaign->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Organization $organization, Campaign $campaign, array $data): Campaign
    {
        $this->assertBelongs($organization, $campaign);
        $this->assertEditable($campaign);

        return DB::transaction(function () use ($actor, $organization, $campaign, $data): Campaign {
            $campaign->fill($this->attributes($organization, $data));
            $campaign->save();

            $this->audience->snapshot($organization, $campaign, $actor);

            return $campaign->refresh();
        });
    }

    public function delete(Organization $organization, Campaign $campaign): void
    {
        $this->assertBelongs($organization, $campaign);

        if (! $campaign->isEditable()) {
            throw ValidationException::withMessages([
                'status' => 'Only draft, ready, or scheduled campaigns can be deleted.',
            ]);
        }

        $campaign->delete();
    }

    public function markReady(User $actor, Organization $organization, Campaign $campaign): Campaign
    {
        $this->assertBelongs($organization, $campaign);

        if (! $campaign->status->canMarkReady()) {
            throw ValidationException::withMessages([
                'status' => 'Only a draft campaign can be marked ready.',
            ]);
        }

        if ($campaign->audience_size < 1) {
            throw ValidationException::withMessages([
                'audience' => 'Add at least one contact to the audience before marking the campaign ready.',
            ]);
        }

        $campaign->status = CampaignStatus::Ready;
        $campaign->save();

        return $campaign;
    }

    public function pause(User $actor, Organization $organization, Campaign $campaign): Campaign
    {
        $this->assertBelongs($organization, $campaign);

        if (! $campaign->status->canPause()) {
            throw ValidationException::withMessages([
                'status' => 'Only a running campaign can be paused.',
            ]);
        }

        $campaign->status = CampaignStatus::Paused;
        $campaign->save();

        return $campaign;
    }

    public function resume(User $actor, Organization $organization, Campaign $campaign): Campaign
    {
        $this->assertBelongs($organization, $campaign);

        if (! $campaign->status->canResume()) {
            throw ValidationException::withMessages([
                'status' => 'Only a paused campaign can be resumed.',
            ]);
        }

        $campaign->status = CampaignStatus::Running;
        $campaign->save();

        return $campaign;
    }

    public function cancel(User $actor, Organization $organization, Campaign $campaign): Campaign
    {
        $this->assertBelongs($organization, $campaign);

        if (! $campaign->status->canCancel()) {
            throw ValidationException::withMessages([
                'status' => 'This campaign can no longer be cancelled.',
            ]);
        }

        $campaign->status = CampaignStatus::Cancelled;
        $campaign->cancelled_at = now();
        $campaign->save();

        return $campaign;
    }

    /**
     * Materialise the campaign recipients from its frozen audience snapshot.
     *
     * No message is sent. The returned counters describe what was recorded.
     *
     * @return array{created: int, skipped: int, total: int}
     */
    public function prepareRecipients(User $actor, Organization $organization, Campaign $campaign): array
    {
        $this->assertBelongs($organization, $campaign);

        if (! $campaign->status->canPrepareRecipients()) {
            throw ValidationException::withMessages([
                'status' => 'Recipients can only be prepared for a draft or ready campaign.',
            ]);
        }

        $snapshot = $campaign->audienceSnapshot;

        if (! $snapshot instanceof CampaignAudienceSnapshot) {
            $snapshot = $this->audience->snapshot($organization, $campaign, $actor);
        }

        /** @var array<int, int> $contactIds */
        $contactIds = $snapshot->contact_ids ?? [];

        return $this->recipients->prepare($organization, $campaign, $contactIds);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Campaign $campaign, bool $withStatistics = false): array
    {
        $campaign->loadMissing(['connectedAccount', 'audienceSnapshot', 'createdBy']);

        $account = $campaign->connectedAccount;
        $creator = $campaign->createdBy;
        $snapshot = $campaign->audienceSnapshot;

        $result = [
            'id' => $campaign->id,
            'organization_id' => $campaign->organization_id,
            'name' => $campaign->name,
            'description' => $campaign->description,
            'type' => $campaign->type->value,
            'type_label' => $campaign->type->label(),
            'channel' => $campaign->channel,
            'status' => $campaign->status->value,
            'status_label' => $campaign->status->label(),
            'message_content' => $campaign->message_content,
            'connected_account_id' => $campaign->connected_account_id,
            'connected_account' => $account instanceof ConnectedAccount ? [
                'id' => $account->id,
                'provider' => $account->provider->value,
                'provider_label' => $account->provider->label(),
                'account_name' => $account->account_name,
                'account_username' => $account->account_username,
                'status' => $account->status->value,
                'status_label' => $account->status->label(),
            ] : null,
            'audience_type' => $campaign->audience_type->value,
            'audience_type_label' => $campaign->audience_type->label(),
            'audience_config' => $campaign->audience_config,
            'audience_size' => $campaign->audience_size,
            'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
            'started_at' => $campaign->started_at?->toIso8601String(),
            'completed_at' => $campaign->completed_at?->toIso8601String(),
            'cancelled_at' => $campaign->cancelled_at?->toIso8601String(),
            'metadata' => $campaign->metadata,
            'created_by_id' => $campaign->created_by_id,
            'created_by' => $creator instanceof User ? [
                'id' => $creator->id,
                'name' => $creator->name,
            ] : null,
            'created_at' => $campaign->created_at?->toIso8601String(),
            'updated_at' => $campaign->updated_at?->toIso8601String(),
            'is_editable' => $campaign->isEditable(),
            'is_terminal' => $campaign->isTerminal(),
            'recipients_count' => $campaign->recipients_count,
            'audience_snapshot' => $snapshot instanceof CampaignAudienceSnapshot ? [
                'id' => $snapshot->id,
                'audience_type' => $snapshot->audience_type->value,
                'contact_count' => $snapshot->contact_count,
                'captured_by_id' => $snapshot->captured_by_id,
                'captured_at' => $snapshot->captured_at?->toIso8601String(),
            ] : null,
            'can' => [
                'edit' => $campaign->status->canEdit(),
                'mark_ready' => $campaign->status->canMarkReady(),
                'pause' => $campaign->status->canPause(),
                'resume' => $campaign->status->canResume(),
                'cancel' => $campaign->status->canCancel(),
                'prepare_recipients' => $campaign->status->canPrepareRecipients(),
            ],
        ];

        if ($withStatistics) {
            $result['statistics'] = $this->statistics->forCampaign($campaign);
        }

        return $result;
    }

    /**
     * Validate ownership and normalise a campaign payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(Organization $organization, array $data): array
    {
        $account = $this->resolveConnectedAccount($organization, $data['connected_account_id'] ?? null);

        $type = CampaignType::from((string) $data['type']);
        $audienceType = CampaignAudienceType::from((string) $data['audience_type']);

        $channel = $data['channel'] ?? null;

        if (($channel === null || $channel === '') && $account instanceof ConnectedAccount) {
            $channel = $account->provider->value;
        }

        return [
            'name' => (string) $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $type,
            'channel' => is_string($channel) && $channel !== '' ? $channel : null,
            'connected_account_id' => $account?->id,
            'message_content' => $data['message_content'] ?? null,
            'audience_type' => $audienceType,
            'audience_config' => $this->audience->normalizeConfig(
                $audienceType,
                is_array($data['audience_config'] ?? null) ? $data['audience_config'] : [],
            ),
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ];
    }

    private function resolveConnectedAccount(Organization $organization, mixed $accountId): ?ConnectedAccount
    {
        if ($accountId === null || $accountId === '') {
            return null;
        }

        $account = ConnectedAccount::query()
            ->where('organization_id', $organization->id)
            ->find((int) $accountId);

        if (! $account instanceof ConnectedAccount) {
            throw ValidationException::withMessages([
                'connected_account_id' => 'The selected connected account does not belong to this organization.',
            ]);
        }

        return $account;
    }

    private function assertBelongs(Organization $organization, Campaign $campaign): void
    {
        if ($campaign->organization_id !== $organization->id) {
            throw new ModelNotFoundException("Campaign [{$campaign->id}] was not found for this organization.");
        }
    }

    private function assertEditable(Campaign $campaign): void
    {
        if (! $campaign->isEditable()) {
            throw ValidationException::withMessages([
                'status' => 'This campaign can no longer be edited.',
            ]);
        }
    }
}
