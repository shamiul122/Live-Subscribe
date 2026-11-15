<?php

namespace App\Services\Campaigns;

use App\Enums\CampaignRecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Organization;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Materialises and reads the per-recipient records of a campaign.
 *
 * Preparing recipients never sends anything. It only records who a campaign
 * intends to reach, with a `pending` status that a future delivery integration
 * may advance.
 */
final class CampaignRecipientService
{
    /**
     * Create recipient rows for the given contact ids.
     *
     * The unique (campaign_id, contact_id) index plus firstOrCreate makes the
     * operation idempotent: running it twice never duplicates a recipient.
     *
     * @param  array<int, int>  $contactIds
     * @return array{created: int, skipped: int, total: int}
     */
    public function prepare(Organization $organization, Campaign $campaign, array $contactIds): array
    {
        $this->assertBelongs($organization, $campaign);

        $unique = array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            $contactIds,
        )));

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($organization, $campaign, $unique, &$created, &$skipped): void {
            foreach ($unique as $contactId) {
                $recipient = CampaignRecipient::query()->firstOrCreate(
                    [
                        'campaign_id' => $campaign->id,
                        'contact_id' => $contactId,
                    ],
                    [
                        'organization_id' => $organization->id,
                        'status' => CampaignRecipientStatus::Pending,
                    ],
                );

                if ($recipient->wasRecentlyCreated) {
                    $created++;
                } else {
                    $skipped++;
                }
            }
        });

        return [
            'created' => $created,
            'skipped' => $skipped,
            'total' => count($unique),
        ];
    }

    /**
     * Organization-scoped recipient listing for a campaign.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(Organization $organization, Campaign $campaign, ?CampaignRecipientStatus $status = null): array
    {
        $this->assertBelongs($organization, $campaign);

        $recipients = CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('organization_id', $organization->id)
            ->with('contact')
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->orderBy('id')
            ->get();

        return $recipients
            ->map(fn (CampaignRecipient $recipient): array => $this->toArray($recipient))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(CampaignRecipient $recipient): array
    {
        $recipient->loadMissing('contact');

        $contact = $recipient->contact;
        $contactSummary = $contact instanceof Contact
            ? [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
            ]
            : null;

        return [
            'id' => $recipient->id,
            'organization_id' => $recipient->organization_id,
            'campaign_id' => $recipient->campaign_id,
            'contact_id' => $recipient->contact_id,
            'status' => $recipient->status->value,
            'status_label' => $recipient->status->label(),
            'delivery_status' => $recipient->delivery_status?->value,
            'delivery_status_label' => $recipient->delivery_status?->label(),
            'error_message' => $recipient->error_message,
            'queued_at' => $recipient->queued_at?->toIso8601String(),
            'sent_at' => $recipient->sent_at?->toIso8601String(),
            'delivered_at' => $recipient->delivered_at?->toIso8601String(),
            'read_at' => $recipient->read_at?->toIso8601String(),
            'failed_at' => $recipient->failed_at?->toIso8601String(),
            'provider_message_id' => $recipient->provider_message_id,
            'metadata' => $recipient->metadata,
            'contact' => $contactSummary,
            'created_at' => $recipient->created_at?->toIso8601String(),
            'updated_at' => $recipient->updated_at?->toIso8601String(),
        ];
    }

    private function assertBelongs(Organization $organization, Campaign $campaign): void
    {
        if ($campaign->organization_id !== $organization->id) {
            throw new ModelNotFoundException("Campaign [{$campaign->id}] was not found for this organization.");
        }
    }
}
