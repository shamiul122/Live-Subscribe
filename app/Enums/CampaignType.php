<?php

namespace App\Enums;

/**
 * The business intent of a marketing campaign.
 */
enum CampaignType: string
{
    case Promotional = 'promotional';
    case ProductAnnouncement = 'product_announcement';
    case CustomerEngagement = 'customer_engagement';
    case FollowUp = 'follow_up';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Promotional => 'Promotional',
            self::ProductAnnouncement => 'Product announcement',
            self::CustomerEngagement => 'Customer engagement',
            self::FollowUp => 'Follow-up',
            self::Custom => 'Custom',
        };
    }

    /**
     * All values accepted by validation rules.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
