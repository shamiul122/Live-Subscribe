<?php

namespace App\Services\Communication;

use App\Contracts\Communication\CommunicationProviderInterface;
use App\Enums\SocialProvider;
use App\Services\Communication\Providers\EmailCommunicationProvider;
use App\Services\Communication\Providers\FacebookCommunicationProvider;
use App\Services\Communication\Providers\InstagramCommunicationProvider;
use App\Services\Communication\Providers\TelegramCommunicationProvider;
use App\Services\Communication\Providers\WhatsAppCommunicationProvider;

final class CommunicationProviderFactory
{
    public function make(SocialProvider $provider): CommunicationProviderInterface
    {
        return match ($provider) {
            SocialProvider::Facebook => new FacebookCommunicationProvider(),
            SocialProvider::Instagram => new InstagramCommunicationProvider(),
            SocialProvider::WhatsApp => new WhatsAppCommunicationProvider(),
            SocialProvider::Telegram => new TelegramCommunicationProvider(),
            SocialProvider::Email => new EmailCommunicationProvider(),
        };
    }
}
