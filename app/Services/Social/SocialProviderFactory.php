<?php

namespace App\Services\Social;

use App\Contracts\Social\SocialAccountProviderInterface;
use App\Services\Social\Providers\EmailProvider;
use App\Services\Social\Providers\FacebookProvider;
use App\Services\Social\Providers\InstagramProvider;
use App\Services\Social\Providers\TelegramProvider;
use App\Services\Social\Providers\WhatsAppProvider;
use App\Enums\SocialProvider;

/**
 * Factory that resolves provider adapter instances by stable identifier.
 */
final class SocialProviderFactory
{
    /**
     * @return SocialAccountProviderInterface
     */
    public function make(SocialProvider $provider): SocialAccountProviderInterface
    {
        return match ($provider) {
            SocialProvider::Facebook => new FacebookProvider(),
            SocialProvider::Instagram => new InstagramProvider(),
            SocialProvider::WhatsApp => new WhatsAppProvider(),
            SocialProvider::Telegram => new TelegramProvider(),
            SocialProvider::Email => new EmailProvider(),
        };
    }
}
