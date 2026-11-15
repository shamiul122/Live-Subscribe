# Marketing Campaigns

Marketing campaigns are an organization-scoped foundation for planning outbound
communication. A campaign describes **intent**: who it should reach, through
which connected account, and with which message. This step does **not** send any
message and never fakes a delivery. Recipients are materialised as `pending`
rows that a later delivery integration may advance.

The feature reuses the existing organization tenancy, `contacts`,
`connected_accounts`, and role model. It does not introduce a second contact
store or a second message pipeline.

## Architecture

| Concern | Location |
|---------|----------|
| HTTP | `app/Http/Controllers/Organizations/Campaigns/` |
| Validation | `app/Http/Requests/Campaigns/` |
| Domain services | `app/Services/Campaigns/` |
| Models | `app/Models/Campaign.php`, `CampaignAudienceSnapshot.php`, `CampaignRecipient.php` |
| Enums | `app/Enums/Campaign{Status,Type,AudienceType,RecipientStatus}.php` |
| Authorization | `app/Policies/CampaignPolicy.php`, `app/Providers/CampaignServiceProvider.php` |
| Routes | `routes/campaigns.php` (registered from `bootstrap/app.php`) |
| Screens | `resources/js/pages/organizations/campaigns/` |
| Tests | `tests/Feature/Campaigns/` |

Services:

- `CampaignService` — create, update, delete, lifecycle transitions, recipient
  preparation, and the serialized campaign payload. All writes run inside a
  database transaction.
- `CampaignAudienceService` — resolves and freezes the audience, estimates its
  size, and lists organization-scoped contacts.
- `CampaignRecipientService` — idempotent recipient materialisation and listing.
- `CampaignStatisticsService` — read-only statistics derived from recipient rows.

## Database structure

### `campaigns`

- `organization_id` (FK, cascade delete), `connected_account_id` (FK, null on
  delete), `created_by_id` (FK, null on delete).
- `name`, `description`, `type` (enum), `channel` (provider key), `status`
  (enum), `message_content`.
- `audience_type` (enum), `audience_config` (JSON), `audience_size`
  (denormalised count of the latest snapshot).
- `scheduled_at`, `started_at`, `completed_at`, `cancelled_at` — timestamps.
  `scheduled_at` is a placeholder for a future scheduler; nothing is dispatched
  from it here.
- `metadata` (JSON), `created_at`, `updated_at`, `deleted_at` (soft deletes).
- Indexes on `(organization_id, status)`, `(organization_id, type)`,
  `(organization_id, channel)`, `(organization_id, created_at)`.

### `campaign_audience_snapshots`

- One row per campaign (`unique(campaign_id)`), rebuilt whenever the campaign is
  created or updated.
- `audience_type`, `filters` (the config as captured), `contact_ids` (the frozen
  list), `contact_count`, `captured_by_id`, `captured_at`.

### `campaign_recipients`

- `organization_id`, `campaign_id`, `contact_id` (FK, null on delete).
- `status` (recipient enum), `delivery_status` (placeholder mirroring
  `MessageDeliveryStatus`), `error_message`.
- `queued_at`, `sent_at`, `delivered_at`, `read_at`, `failed_at`,
  `provider_message_id`, `metadata`.
- `unique(campaign_id, contact_id)` guarantees a contact is a recipient of a
  campaign at most once.

## Lifecycle

Statuses: `draft`, `ready`, `scheduled`, `running`, `paused`, `completed`,
`cancelled`, `failed`. Transitions are validated by
`CampaignStatus` and enforced by `CampaignService`; an invalid transition throws
a validation error (HTTP 422) instead of silently changing state.

| From | Action | To |
|------|--------|----|
| draft | mark ready (needs `audience_size >= 1`) | ready |
| running | pause | paused |
| paused | resume | running |
| draft / ready / scheduled / running / paused | cancel | cancelled |

Rules:

- `completed`, `cancelled`, and `failed` are **terminal**. A terminal campaign
  cannot be edited, deleted, cancelled, or restarted.
- Editing and deletion are only allowed while `canEdit()` (draft, ready,
  scheduled).
- Recipients may only be prepared while the campaign is draft or ready.
- `scheduled`, `running`, `completed`, `failed`, and the actual delivery
  advancement are reserved for the future scheduler/delivery step; this step
  exposes the states but does not fabricate the behaviour behind them.

## Audience snapshot behaviour

1. The submitted `audience_type` is `all` or `selected`.
   - `all` resolves every contact of the organization.
   - `selected` resolves the submitted `audience_config.contact_ids`, which must
     all belong to the organization. A foreign contact id is rejected, not
     silently dropped.
2. Contact ids are de-duplicated and stored on the single snapshot row.
3. `campaigns.audience_size` is refreshed from the snapshot count.
4. The snapshot is **frozen**: a later contact edit, addition, or soft delete
   does not change the audience of an existing campaign. Recipients are created
   from the frozen ids, not from a fresh query.

Tag-, source-, and status-based segmentation are intentionally **not** offered:
the current contact schema has no such fields, so presenting them would be
fake functionality.

### Audience preview endpoint

`POST /organizations/{organization}/campaigns/audience/preview` returns
`{ audience_type, count, contact_ids }` for the current form state. The estimate
is server-authoritative and is never trusted for storage — the campaign service
rebuilds and validates the snapshot on save. The form calls it with `fetch`
using the `XSRF-TOKEN` cookie, because the application does not render a CSRF
meta tag.

## Recipient lifecycle

Recipient statuses: `pending`, `queued`, `sending`, `sent`, `delivered`, `read`,
`failed`, `skipped`. `CampaignRecipientStatus::rank()` defines a monotonic order
so a future out-of-order delivery callback cannot move a recipient backwards.

Preparing recipients:

- Idempotent. `firstOrCreate` on `(campaign_id, contact_id)` means running it
  twice creates nothing the second time and reports the skips.
- Records `pending` rows only. **No message is sent, queued, or marked as
  delivered by this step.**
- Returns `{ created, skipped, total }` and never invents delivery data.

## Authorization

Gates are registered as named gates (`campaign.view`, `campaign.manage`) by
`CampaignServiceProvider` against `CampaignPolicy`, following the existing inbox
policy pattern (the policy takes an `Organization`, not a `Campaign`, so
organization scoping stays in one place).

- `campaign.view` — any active member. Applies to the index, show, and
  recipients screens.
- `campaign.manage` — owner or admin. Applies to create, edit, delete, lifecycle
  transitions, recipient preparation, and the audience preview.

Organization scoping and IDOR protection:

- The `organization.member` middleware ensures the acting user belongs to the
  organization in the URL.
- Every service method re-checks `campaign.organization_id` against the
  organization and throws `ModelNotFoundException` (HTTP 404) on mismatch, so a
  campaign id from another organization is indistinguishable from a missing one.
- The selected connected account must belong to the organization or the request
  fails validation.
- Selected contacts must belong to the organization or the request fails
  validation.

## Communication engine integration boundary

This step stops exactly where the communication engine (see
`docs/COMMUNICATION_ENGINE.md`) begins.

- Campaigns and recipients never call `OutboundMessageService`, a provider
  adapter, or a queue job.
- `campaign_recipients.delivery_status`, `provider_message_id`, and the
  `*_at` timestamps are placeholders that mirror the engine's shapes so a future
  integration can fill them without a schema change.
- `CampaignRecipientStatus` is deliberately separate from
  `MessageDeliveryStatus`: a campaign recipient aggregates a delivery, it is not
  a message itself.

Future integration points:

1. A scheduler that turns `scheduled_at` into a `scheduled`/`running` transition.
2. A dispatcher that maps `pending` recipients to `OutboundMessageService` calls
   and writes back `provider_message_id` and `delivery_status`.
3. Delivery callbacks that advance recipient status using `rank()` and update
   `completed_at` when every recipient is terminal.
4. Plan/entitlement gating of campaign volume, mirroring the billing work.
5. Tag/source/status segmentation once the contact schema supports it.

## Testing

Tests live in `tests/Feature/Campaigns/` and cover creation, org isolation,
unauthorized access, contact and connected-account ownership, the frozen
audience snapshot, duplicate-recipient prevention, lifecycle transitions,
recipient status handling, and statistics.

```bash
php artisan test --filter=Campaigns
```

## Known limitations

- No message is sent and no delivery is simulated.
- `scheduled_at` is stored but not acted upon.
- Audience segmentation is limited to all contacts or an explicit selection.
- Statistics reflect stored recipient statuses only; an empty campaign reports
  zeroes.
