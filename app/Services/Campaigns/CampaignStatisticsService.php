<?php

namespace App\Services\Campaigns;

use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Organization;

/**
 * Read-only campaign statistics derived from recipient records.
 *
 * Statistics are calculated from stored statuses only. Nothing is inferred or
 * fabricated: an empty campaign reports zeroes.
 */
final class CampaignStatisticsService
{
    /**
     * @return array<string, mixed>
     */
    public function forCampaign(Campaign $campaign): array
    {
        $grouped = CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStatus = [];
        $total = 0;

        foreach (CampaignRecipientStatus::cases() as $status) {
            $count = (int) ($grouped[$status->value] ?? 0);
            $byStatus[$status->value] = $count;
            $total += $count;
        }

        $delivered = $byStatus[CampaignRecipientStatus::Delivered->value]
            + $byStatus[CampaignRecipientStatus::Read->value];
        $failed = $byStatus[CampaignRecipientStatus::Failed->value];

        return [
            'total' => $total,
            'pending' => $byStatus[CampaignRecipientStatus::Pending->value],
            'queued' => $byStatus[CampaignRecipientStatus::Queued->value],
            'sending' => $byStatus[CampaignRecipientStatus::Sending->value],
            'sent' => $byStatus[CampaignRecipientStatus::Sent->value],
            'delivered' => $delivered,
            'failed' => $failed,
            'skipped' => $byStatus[CampaignRecipientStatus::Skipped->value],
            'by_status' => $byStatus,
            'delivery_rate' => $total > 0 ? round($delivered / $total * 100, 2) : 0.0,
            'has_recipients' => $total > 0,
        ];
    }

    /**
     * Organization-wide campaign totals grouped by status.
     *
     * @return array<string, mixed>
     */
    public function organizationSummary(Organization $organization): array
    {
        $grouped = Campaign::query()
            ->where('organization_id', $organization->id)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStatus = [];

        foreach (CampaignStatus::cases() as $status) {
            $byStatus[$status->value] = (int) ($grouped[$status->value] ?? 0);
        }

        return [
            'total' => array_sum($byStatus),
            'by_status' => $byStatus,
        ];
    }
}
