<?php

namespace App\Enums;

/**
 * How a campaign selects the contacts it intends to reach.
 *
 * The foundation intentionally supports the two unambiguous selectors. Tag,
 * source, and customer-status segmentation are not part of the current contact
 * schema, so they are not offered as fake options here.
 */
enum CampaignAudienceType: string
{
    case All = 'all';
    case Selected = 'selected';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All contacts',
            self::Selected => 'Selected contacts',
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
