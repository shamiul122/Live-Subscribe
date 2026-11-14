<?php

namespace App\Enums;

/**
 * Social account provider identifiers supported by the connected accounts
 * foundation. The value is the stable machine key used in storage, the label
 * is the human-facing display name.
 */
enum SocialProvider: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case WhatsApp = 'whatsapp';
    case Telegram = 'telegram';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::WhatsApp => 'WhatsApp',
            self::Telegram => 'Telegram',
            self::Email => 'Email',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
