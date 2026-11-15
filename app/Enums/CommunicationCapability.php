<?php

namespace App\Enums;

/**
 * Capabilities a communication adapter may declare.
 *
 * Adapters must only advertise a capability they actually implement. A false
 * value (or a reason from capabilityReason()) is surfaced to the UI instead of
 * pretending the operation is available.
 */
enum CommunicationCapability: string
{
    case OutboundText = 'outbound_text';
    case Attachments = 'attachments';
    case DeliveryStatus = 'delivery_status';
    case InboundWebhooks = 'inbound_webhooks';

    public function label(): string
    {
        return match ($this) {
            self::OutboundText => 'Outbound text messages',
            self::Attachments => 'Attachments',
            self::DeliveryStatus => 'Delivery status callbacks',
            self::InboundWebhooks => 'Inbound webhooks',
        };
    }
}
