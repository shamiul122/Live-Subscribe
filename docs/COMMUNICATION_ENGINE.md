# Communication Engine

The communication engine extends the Step 5 unified inbox with real provider
adapters. It does not introduce a second inbox or message store: outbound and
inbound messages still live in `messages`, inside the existing
organization-scoped `conversations`.

## What is implemented

- Outbound messages are validated, persisted as `queued`, and dispatched to a
  single-attempt job. The job atomically claims the row before calling a
  provider, so a duplicate dispatch cannot send the same message twice.
- Idempotency keys are stored on the message and enforced by a unique index.
  Replaying the same key returns the existing message instead of sending again.
- Inbound webhooks are verified, stored in `webhook_events` with a redacted
  payload, deduplicated by payload hash and provider event id, then normalized
  into contacts, conversations, and messages.
- Delivery callbacks update `messages` and `delivery_attempts` monotonically.
  A delayed `sent` callback cannot move a `read` message backwards.
- Attachments are stored on the configured disk and linked to outbound
  messages. Inbound provider media is recorded as metadata
  (`inbound_attachments`) and is not downloaded or fabricated as a local file.
- Internal notifications are database notifications and are enabled by
  `COMMUNICATION_NOTIFICATIONS`.
- Failed outbound messages expose an explicit retry action in the inbox.
  Network failures are never retried automatically.

Provider success means **provider acceptance**, not inbox delivery. A message
is only marked `delivered` or `read` after a provider callback says so.

## Provider capability matrix

| Provider  | Outbound text | Outbound attachments | Delivery status | Inbound webhooks |
|-----------|---------------|----------------------|-----------------|------------------|
| Facebook  | Yes           | No                   | Yes             | Yes              |
| Instagram | Yes           | No                   | Yes             | Yes              |
| WhatsApp  | Yes           | Yes                  | Yes             | Yes              |
| Telegram  | Yes           | Yes                  | No              | Yes              |
| Email     | Yes           | Yes                  | No              | No               |

Why the gaps:

- **Facebook/Instagram attachments** require a publicly reachable media URL.
  The adapters deliberately report `attachments: false` with an honest reason
  instead of pretending to send files.
- **Telegram delivery status** is not exposed by the Bot API.
- **Email** uses Laravel's configured mail transport. With
  `MAIL_MAILER=log` (the default in development) the adapter reports the
  provider as unconfigured, and no mail is sent. Inbound email parsing and
  delivery receipts are not implemented.
- **WhatsApp templates**: business-initiated messages outside the 24-hour
  customer service window require approved templates. This engine does not
  create or manage templates.

Capability reasons are returned with the conversation props so the composer
can disable unsupported actions and explain why.

## Configuration

Provider credentials live in `config/social.php` and are read from the
environment. Leave them empty to keep a provider disabled; no provider is
contacted while its credentials are blank.

```dotenv
FACEBOOK_CLIENT_ID=
FACEBOOK_CLIENT_SECRET=
FACEBOOK_APP_SECRET=
FACEBOOK_WEBHOOK_VERIFY_TOKEN=

INSTAGRAM_CLIENT_ID=
INSTAGRAM_CLIENT_SECRET=
INSTAGRAM_APP_SECRET=
INSTAGRAM_WEBHOOK_VERIFY_TOKEN=

WHATSAPP_ACCESS_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_APP_SECRET=
WHATSAPP_WEBHOOK_VERIFY_TOKEN=

TELEGRAM_BOT_TOKEN=
TELEGRAM_WEBHOOK_SECRET_TOKEN=

COMMUNICATION_QUEUE=default
COMMUNICATION_ATTACHMENT_DISK=local
COMMUNICATION_ATTACHMENT_MAX_KB=10240
COMMUNICATION_ATTACHMENT_MAX_PER_MESSAGE=5
COMMUNICATION_NOTIFICATIONS=true
```

The provider app secret (`FACEBOOK_APP_SECRET`, `INSTAGRAM_APP_SECRET`,
`WHATSAPP_APP_SECRET`) is required for Meta webhook signature verification.
The client secret is only used as a fallback.

## Webhook setup

Webhook endpoints are public because providers cannot send a browser session.
CSRF is exempted for `webhooks/*`; every request still has to pass the
provider's own verification.

- **Facebook**: callback URL `/webhooks/facebook`, subscribed to the `messages`
  field. Configure the verify token in `FACEBOOK_WEBHOOK_VERIFY_TOKEN`.
  POST payloads are verified with `X-Hub-Signature-256`.
- **Instagram**: callback URL `/webhooks/instagram`, `messages` field. Both the
  Messenger `messaging` shape and the `changes[].value` shape are normalized.
- **WhatsApp**: callback URL `/webhooks/whatsapp`, subscribed to `messages`.
  Both inbound messages and `statuses` callbacks are processed.
- **Telegram**: set the webhook with
  `https://api.telegram.org/bot<TOKEN>/setWebhook?url=<APP_URL>/webhooks/telegram?account=<connected_account_id>&secret_token=<TOKEN>`.
  The `account` query parameter is required and must reference an active
  Telegram connected account. The request is verified with
  `X-Telegram-Bot-Api-Secret-Token`.
- **Email**: no webhook endpoint. `POST /webhooks/email` returns
  `capability_not_supported`.

Requests to unknown providers return `404`. Invalid signatures, missing
accounts, and unsupported capabilities return `403` and are not persisted.

## Queue behaviour

The default queue connection is used. In production run at least one worker:

```bash
php artisan queue:work
```

`SendMessageJob` declares `$tries = 1`. Do not override this with a higher
`--tries` value: ambiguous network failures must be reviewed and retried
explicitly from the inbox. The job is also unique for ten minutes by
idempotency key.

`ProcessInboundWebhookJob` records processing failures on the `webhook_events`
row instead of silently dropping them. Check `processing_status` and
`failure_reason` when an inbound event does not appear in the inbox.

## Data recorded

- `messages`: body, direction, type, delivery status, provider message id,
  timestamps, failure reason, metadata including inbound attachment descriptors.
- `delivery_attempts`: one row per provider attempt, with status, error code,
  error message, and provider message id.
- `webhook_events`: provider, connected account, event type/id, payload hash,
  redacted payload, processing status, attempts, failure reason.
- `attachments`: organization, message, original name, disk/path, MIME type,
  size, checksum, and uploader.

Credential-like keys (`token`, `secret`, `password`, `authorization`,
`signature`) are redacted before a webhook payload is stored. Tokens are never
logged or returned in API responses.

## Failure handling

- Unconfigured provider accounts are rejected before a message is persisted.
- Provider rejection records the provider error message, a structured error
  code, and a failed delivery attempt.
- Meta OAuth errors (codes `102`, `190`, `463`, `467` or `OAuthException`) map to
  `invalid_account_credentials` even when Meta returns HTTP 400.
- A provider call that throws a transport error is marked failed with a manual
  retry hint. It is not retried by the queue.
- Partial multipart failure is reported as `Partially sent (...)`; the message
  remains failed so the operator can decide whether to resend.

## Testing

The automated suite uses `Http::fake()` and signed raw webhook bodies. No real
provider credentials are required. Relevant tests live in
`tests/Feature/Communication/`.

Run the focused suite with:

```bash
php artisan test --filter=Communication
```

## Known limitations

- Facebook and Instagram outbound attachments are disabled.
- Telegram has no delivery or read receipts.
- Email has no inbound parsing and no delivery receipts.
- WhatsApp template creation/management is out of scope.
- Inbound media is referenced by provider id/URL metadata; it is not copied to
  local storage.
- Notification channels are database-only.
- Outbound entitlement/plan limits are not integrated in this step; see
  `docs/` billing work before gating message volume by plan.
