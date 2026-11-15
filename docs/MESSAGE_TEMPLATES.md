# Message Templates

Message templates are an organization-scoped, reusable library of message
content. A template describes **content only**: a name, an optional subject, a
body, and the placeholders it uses. Templates are rendered on demand and are
**never sent automatically**; they exist to be reused by other modules such as
the campaign builder and, later, the inbox.

The feature reuses the existing organization tenancy, the provider/channel enum
(`App\Enums\SocialProvider`), and the role model. It does not introduce a second
provider definition or a second message pipeline.

## Architecture

| Concern | Location |
|---------|----------|
| HTTP | `app/Http/Controllers/Organizations/Templates/` |
| Validation | `app/Http/Requests/Templates/` |
| Domain services | `app/Services/Templates/` |
| Model | `app/Models/MessageTemplate.php` |
| Enums | `app/Enums/MessageTemplate{Status,Category}.php` |
| Authorization | `app/Policies/MessageTemplatePolicy.php`, `app/Providers/TemplateServiceProvider.php` |
| Routes | `routes/templates.php` (registered from `bootstrap/app.php`) |
| Screens | `resources/js/pages/organizations/message-templates/` |
| Shared form | `resources/js/components/templates/MessageTemplateForm.vue` |
| Types | `resources/js/types/message-template.ts` |
| Tests | `tests/Feature/Templates/` |

Services:

- `MessageTemplateService` — create, update, archive/restore, soft delete,
  organization-scoped lookups, listing, the reusable-template feed, and the
  serialized payload. All writes run inside a database transaction.
- `TemplateVariableService` — the supported placeholder vocabulary, detection,
  unknown-variable reporting, and safe sample values.
- `TemplateRendererService` — plain string substitution, preview rendering, and
  value normalization.

## Database structure

### `message_templates`

- `organization_id` (FK, cascade delete), `created_by_id` (FK, null on delete).
- `name`, `description`, `channel` (provider key, nullable), `category` (enum),
  `status` (enum).
- `subject` (nullable, used for email), `content` (required).
- `variables` (JSON) — the detected placeholder keys, **derived from the content
  on every save** so the stored list can never drift from the body.
- `metadata` (JSON), `created_at`, `updated_at`, `deleted_at` (soft deletes).
- Indexes on `(organization_id, status)`, `(organization_id, channel)`,
  `(organization_id, category)`, `(organization_id, created_at)`.

## Status and categories

Statuses (`App\Enums\MessageTemplateStatus`):

- `draft` — work in progress.
- `active` — ready to be reused by other modules.
- `archived` — read-only; hidden from pickers until restored.

Rules:

- Only `active` templates are offered to other modules (`isReusable()`).
- `archived` templates cannot be edited; the update endpoint redirects to the
  details page with a message and the service rejects the write with a 422. The
  `restore` action returns an archived template to `draft`.

Categories (`App\Enums\MessageTemplateCategory`): `promotional`, `welcome`,
`follow_up`, `customer_support`, `order_update`, `product_announcement`,
`custom`.

Channels reuse `App\Enums\SocialProvider`: `facebook`, `instagram`, `whatsapp`,
`telegram`, `email`. A template may also have **no** channel, meaning it is
channel-agnostic.

## Template variables

Supported placeholders:

| Placeholder | Sample value |
|-------------|--------------|
| `{{customer_name}}` | Jane Doe |
| `{{customer_email}}` | jane@example.com |
| `{{product_name}}` | Acme Pro Plan |
| `{{order_number}}` | ORD-1042 |
| `{{company_name}}` | Acme Inc. |

Behaviour:

- **Detection** — `TemplateVariableService::detect()` finds `{{ snake_case }}`
  tokens with the pattern `/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/`. Only word characters
  are accepted, so a token cannot smuggle an expression.
- **Validation** — the store/update and preview requests reject unknown
  placeholders with a `content` validation error (HTTP 422). A malformed token
  that does not match the pattern is treated as literal text.
- **Rendering** — `TemplateRendererService::render()` performs a plain
  `preg_replace_callback`. There is **no `eval`, no `include`, and no expression
  parser**. Caller-supplied values win, otherwise the safe sample value is used,
  otherwise an unknown token is left visible rather than dropped.
- **Sample values** — the editor and preview screens seed every input with the
  sample value, so a preview is always meaningful.

## Screens

- **Index** — search by name/description/content plus channel, category, and
  status filters. Shows status badges and per-row preview/edit/archive/restore/
  delete actions (management actions are hidden from members).
- **Create / Edit** — a shared form with channel, category, status, subject, and
  content, an "available variables" list that inserts a token at the cursor, and
  a live preview rendered by the server.
- **Details** — full content, the detected variables with sample values, and the
  lifecycle actions.
- **Preview** — editable sample values for the variables actually used, with the
  rendered subject and body. Uses the same render endpoint as the form.

The live preview and the preview screen call
`POST /organizations/{organization}/message-templates/preview` with `fetch` and
the `XSRF-TOKEN` cookie, because the application does not render a CSRF meta tag.

## Authorization

Gates are registered as named gates (`message_template.view`,
`message_template.manage`) by `TemplateServiceProvider` against
`MessageTemplatePolicy`, following the campaign/inbox policy pattern (the policy
takes an `Organization`, not a template, so organization scoping stays in one
place).

- `message_template.view` — any active member. Applies to the index, details, and
  preview screens.
- `message_template.manage` — owner or admin. Applies to create, edit, archive,
  restore, delete, and the render endpoint.

Organization scoping and IDOR protection:

- The `organization.member` middleware ensures the acting user belongs to the
  organization in the URL.
- Every service method re-checks `message_template.organization_id` against the
  organization and throws `ModelNotFoundException` (HTTP 404) on mismatch, so a
  template id from another organization is indistinguishable from a missing one.

## Module integration

Campaign management consumes active templates:

- `CampaignController` passes `templates` (active, organization-scoped) to the
  create/edit screens.
- `CampaignForm.vue` offers a template picker that copies the template body into
  the campaign `message_content` and adopts its channel. Placeholders are copied
  verbatim — nothing is rendered or sent at this point.

The module stops exactly where the communication engine begins: it never calls
`OutboundMessageService`, a provider adapter, or a queue job.

## Testing

Tests live in `tests/Feature/Templates/` and cover creation and variable
detection, organization isolation, archived-template protection, the reusable
feed, unknown-variable reporting and rejection, rendering with supplied and
sample values, safe handling of unknown tokens, soft deletion, HTTP access
control, and the campaign template feed.

```bash
php artisan test --filter=Templates
```

## Known limitations

- No message is sent and no delivery is simulated.
- Rendering is plain substitution; there are no conditionals, loops, or
  formatting helpers.
- Only the five documented placeholders are supported; any other token is
  rejected on save.
