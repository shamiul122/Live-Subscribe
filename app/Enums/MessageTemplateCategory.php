<?php

namespace App\Enums;

/**
 * The business intent of a message template. Categories group templates in the
 * picker and make searching predictable, they do not change rendering.
 */
enum MessageTemplateCategory: string
{
    case Promotional = 'promotional';
    case Welcome = 'welcome';
    case FollowUp = 'follow_up';
    case CustomerSupport = 'customer_support';
    case OrderUpdate = 'order_update';
    case ProductAnnouncement = 'product_announcement';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Promotional => 'Promotional',
            self::Welcome => 'Welcome',
            self::FollowUp => 'Follow-up',
            self::CustomerSupport => 'Customer support',
            self::OrderUpdate => 'Order update',
            self::ProductAnnouncement => 'Product announcement',
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
