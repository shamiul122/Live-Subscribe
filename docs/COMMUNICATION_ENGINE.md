# Communication Engine

## Architecture

The Communication Engine connects the Step 4 Connected Social Accounts foundation with the
Step 5 Unified Inbox. It provides a provider-independent architecture for:

- Receiving inbound messages through webhooks
- Sending outbound messages through provider adapters
- Processing messages asynchronously using Laravel queues
- Handling delivery status and failures
- Processing attachments and media
- Sending internal notifications
- Recording communication events and delivery attempts

### Directory Structure

```
app/
  Contracts/Communication/
    CommunicationProviderInterface.php
  Services/Communication/
    AbstractCommunicationProvider.php
    CommunicationProviderRegistry.php
    CommunicationProviderFactory.php
    OutboundMessageService.php
    DTOs/
      InboundMessageData.php
      ProviderSendResult.php
    Providers/
      FacebookCommunicationProvider.php
      InstagramCommunicationProvider.php
      WhatsAppCommunicationProvider.php
      TelegramCommunicationProvider.php
      EmailCommunicationProvider.php
  Jobs/Communication/
    SendMessageJob.php
    ProcessInboundWebhookJob.php
  Http/Controllers/
    WebhookController.php
    Organizations/Inbox/InboxMessageController.php
    Organizations/Inbox/InboxAttachmentController.php
  Models/
    WebhookEvent.php
    DeliveryAttempt.php
    Attachment.php
  Notifications/
    NewInboundMessageNotification.php
```

### Provider Adapter Registration

Adapters are registered in `CommunicationProviderRegistry::registerDefaults()` which is called
when the registry singleton is created in `AppServiceProvider::register()`. Each adapter
implements `CommunicationProviderInterface` and declares its capabilities.

### Required Provider Credentials

Configure these in `.env` or `config/social.php`:

- `FACEBOOK_CLIENT_ID`, `FACEBOOK_CLIENT_SECRET`, `FACEBOOK_WEBHOOK_VERIFY_TOKEN`
- `INSTAGRAM_CLIENT_ID`, `INSTAGRAM_CLIENT_SECRET`, `INSTAGRAM_WEBHOOK_VERIFY_TOKEN`
- `WHATSAPP_ACCESS_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_WEBHOOK_VERIFY_TOKEN`
- `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET_TOKEN`
- `EMAIL_API_KEY`, `EMAIL_FROM_ADDRESS`, `EMAIL_API_BASE_URL`, `EMAIL_WEBHOOK_VERIFY_TOKEN`

### Webhook Setup Requirements

Each provider requires webhook subscription configuration:

- **Facebook/Instagram**: Subscribe to `messages` and `messaging_postbacks` events. Set callback URL to `/webhooks/facebook` or `/webhooks/instagram`. Verify token must match `FACEBOOK_WEBHOOK_VERIFY_TOKEN` / `INSTAGRAM_WEBHOOK_VERIFY_TOKEN`.
- **WhatsApp**: Configure webhook URL `/webhooks/whatsapp` with verify token. Subscribe to `messages` and `message_status` events.
- **Telegram**: Set webhook via Bot API: `https://api.telegram.org/bot<TOKEN>/setWebhook?url=<URL>/webhooks/telegram`. Optional secret token via `TELEGRAM_WEBHOOK_SECRET_TOKEN`.
- **Email**: Configure inbound parse webhook URL `/webhooks/email` with optional `EMAIL_WEBHOOK_VERIFY_TOKEN`.

Webhook endpoints do not require browser authentication but enforce provider-specific verification.

### Queue Configuration and Worker Command

The communication engine uses Laravel's existing queue system. Run a queue worker:

```bash
php artisan queue:work --tries=3 --backoff=30
```

For production, use Supervisor or systemd to keep the worker running.

### Supported Capabilities by Provider

| Provider   | Outbound Text | Attachments | Delivery Status | Inbound Webhooks |
|------------|---------------|-------------|-----------------|-----------------|
| Facebook   | Yes           | Yes         | Yes             | Yes             |
| Instagram  | Yes           | Yes         | Yes             | Yes             |
| WhatsApp   | Yes           | Yes         | Yes             | Yes             |
| Telegram   | Yes           | Yes         | No              | Yes             |
| Email      | Yes           | Yes         | Yes             | Yes             |

### Unsupported Capabilities and Reasons

- **Telegram delivery status**: Telegram Bot API does not provide delivery/read receipts for messages.
- **Real provider integration**: Requires valid provider credentials and API permissions. Adapters return structured failures (`provider_not_configured`, `capability_not_supported`, etc.) when unconfigured.
- **Template messages**: WhatsApp requires approved templates for certain message types. Not implemented in this foundation.
- **Media upload**: Provider-specific media upload is adapter-specific and not fully wired in this foundation.

### Local Development Testing Approach

- Use `Http::fake()` for provider API tests.
- Webhook tests can POST to `/webhooks/{provider}` with test payloads.
- Queue jobs can be tested with `Queue::fake()`.
- No real provider credentials required for automated tests.

### Production Deployment Considerations

- Configure queue worker with Supervisor/systemd.
- Set up webhook URLs in provider dashboards.
- Ensure `APP_URL` is set correctly for webhook callbacks.
- Monitor `webhook_events` and `delivery_attempts` tables for failures.
- Configure log channels for communication errors.
- Rotate provider credentials periodically.

### Known Limitations

- Real provider API calls require valid credentials.
- WhatsApp template messages not supported.
- Telegram delivery status not available.
- Media upload/download is foundation-level only.
- Notification channels are database-only (no email/SMS unless configured).
- Webhook event processing stores minimal payload data (hash only for deduplication).
