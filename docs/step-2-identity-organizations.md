# Step 2 — Identity & Organizations

This document describes the identity and organization module delivered in Step 2.
It covers the data model, authorization rules, domain actions, HTTP surface,
invitation lifecycle, soft-delete policy and verification results.

## Scope

- Users remain a **global** identity. `users` has no `organization_id`.
- Ownership lives in `organizations.owner_id`; that column is the single source
  of truth for who owns an organization.
- Organization roles are **not** managed through Spatie. Laravel policies and
  domain actions enforce organization authorization.
- No organization API endpoints were added. `GET /api/user` is unchanged.
- All backend mutations run through transactional domain actions.

## Architecture

### Roles and precedence

Only two roles are stored on memberships:

| Stored role | Meaning |
| --- | --- |
| `admin` | Administrative member |
| `member` | Ordinary member |

The owner is not a stored role. Effective privilege is resolved in this order:

1. **Owner** — `organizations.owner_id === user.id`
2. **Admin** — active membership with role `admin`
3. **Member** — active membership with role `member`
4. **Non-member** — no active membership

The owner always has the effective `admin` role, and their owner membership row
is kept as an active `admin` membership. Submitted roles are never trusted;
requests are validated against `App\Enums\OrganizationRole`, and privilege
escalation is blocked in the domain actions.

### Membership status

`organization_memberships.status` is `active` or `inactive`.

- Policies and middleware only consider **active** memberships.
- Inactive memberships are retained as history and can be reactivated when an
  invitation is accepted.
- Removing a member deletes the membership row rather than marking it inactive.

### Soft deletes and slug policy

Organizations use `SoftDeletes`.

- Slugs are generated with `App\Support\OrganizationSlug`, which checks
  `Organization::withTrashed()`.
- Soft-deleted organizations therefore keep their slug permanently reserved.
  This prevents stale public URLs from resolving to a different organization.
- Deleting an organization soft-deletes the row, revokes pending invitations and
  keeps memberships so a restore preserves membership history.
- Deleted organizations are not reachable through route model binding, the
  organization index, or shared Inertia props.

### Email normalization

Emails are normalized (trimmed and lower-cased) in application code via
`App\Support\EmailNormalizer`. This keeps uniqueness checks and invitation
matching independent of database collation.

- `User` normalizes email through an Eloquent mutator.
- `OrganizationInvitation` normalizes email through an Eloquent mutator.
- Invitations are matched by normalized email, not by database collation.

### Invitation tokens

- Raw invitation tokens are generated with `Str::random(64)`.
- Only `token_hash = sha256(token)` is persisted.
- The raw token is exposed only on `OrganizationInvitation::$plainToken` in
  memory and through the generated invitation URL.
- Tokens are unique at the database level through `organization_invitations.token_hash`.

### Invitation delivery

Invitations are dispatched through
`App\Notifications\OrganizationInvitationNotification` using Laravel's on-demand
`mail` channel. The recipient is the normalized invitation email, and the
notification links to the same invitation URL that is returned to the inviter.

Actual delivery is **not verified** in this step:

- `.env.example` configures `MAIL_MAILER=log`, and the test environment uses
  `MAIL_MAILER=array` together with `Notification::fake()`.
- The tests assert that the notification is dispatched to the correct email
  address, not that a real message reaches an inbox.
- The organization page therefore also displays the invitation URL so it can be
  shared manually when no mail transport is configured.

Real mail transport configuration and inbox delivery verification remain out of
scope.

## Database schema

Migrations:

- `2026_11_14_000001_create_organizations_table.php`
- `2026_11_14_000002_create_organization_memberships_table.php`
- `2026_11_14_000003_create_organization_invitations_table.php`

### `organizations`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK | |
| `owner_id` | bigint FK → `users.id` | `restrictOnDelete` |
| `name` | string | |
| `slug` | string, unique | Reserved across soft-deleted rows |
| `created_at`, `updated_at` | timestamps | |
| `deleted_at` | timestamp nullable | Soft delete marker |

Indexes: primary key, unique `slug`, index on `owner_id`.

`organization_id` is intentionally **not** added to `users`.

### `organization_memberships`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK | |
| `organization_id` | bigint FK → `organizations.id` | `cascadeOnDelete` |
| `user_id` | bigint FK → `users.id` | `cascadeOnDelete` |
| `role` | string(20), default `member` | `admin` or `member` |
| `status` | string(20), default `active` | `active` or `inactive` |
| `joined_at` | timestamp nullable | |
| `created_at`, `updated_at` | timestamps | |

Constraints and indexes:

- Unique `(organization_id, user_id)` — one membership row per user per organization.
- Index `(organization_id, status)`.
- Index `(user_id, status)`.

### `organization_invitations`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | bigint PK | |
| `organization_id` | bigint FK → `organizations.id` | `cascadeOnDelete` |
| `invited_by_id` | bigint FK → `users.id` | `cascadeOnDelete` |
| `accepted_by_id` | bigint FK → `users.id`, nullable | `nullOnDelete` |
| `email` | string | Normalized in application code |
| `role` | string(20), default `member` | `admin` or `member` |
| `token_hash` | string(64), unique | SHA-256 of the raw token |
| `expires_at` | timestamp | Default lifetime is 7 days |
| `accepted_at` | timestamp nullable | |
| `revoked_at` | timestamp nullable | |
| `created_at`, `updated_at` | timestamps | |

Indexes: unique `token_hash`, index `(organization_id, email)`,
index `(organization_id, accepted_at)`, index `email`.

### Deletion behavior

| Foreign key | Behavior |
| --- | --- |
| `organizations.owner_id → users.id` | Restrict |
| `organization_memberships.organization_id → organizations.id` | Cascade |
| `organization_memberships.user_id → users.id` | Cascade |
| `organization_invitations.organization_id → organizations.id` | Cascade |
| `organization_invitations.invited_by_id → users.id` | Cascade |
| `organization_invitations.accepted_by_id → users.id` | Set null |

Because ownership is restricted, an account cannot be deleted while it owns an
organization. The account deletion flow blocks this in the application and also
checks trashed organizations.

## Authorization matrix

Policy: `App\Policies\OrganizationPolicy`, registered in `AppServiceProvider`.
Role resolution and membership checks are centralized in `Organization` model
helpers (`isOwnedBy`, `hasMember`, `activeMembershipFor`, `roleFor`).

| Ability | Owner | Admin | Member | Non-member |
| --- | --- | --- | --- | --- |
| View organization | Yes | Yes | Yes | No |
| Update organization | Yes | Yes | No | No |
| Delete organization | Yes | No | No | No |
| Transfer ownership | Yes | No | No | No |
| Invite members | Yes | Yes | No | No |
| Invite another admin | Yes | No | No | No |
| Revoke invitations | Yes | Yes | No | No |
| Change member roles | Yes | No | No | No |
| Remove ordinary members | Yes | Yes | No | No |
| Remove admins | Yes | No | No | No |
| Leave organization | No | Yes | Yes | No |

Additional invariants enforced by actions:

- The owner membership always remains an active `admin` membership.
- The owner cannot be removed and cannot leave.
- Ownership transfer keeps the previous owner as an active `admin`.
- Ownership can only be transferred to an active member.
- Admins cannot grant or revoke the `admin` role.
- Admins cannot remove other admins.
- Users cannot remove themselves through the member-removal action; they must
  use the leave action.

`App\Http\Middleware\EnsureOrganizationMembership` (alias `organization.member`)
rejects unauthenticated users and non-members before organization routes run.
Controllers additionally authorize specific abilities through the policy.

## HTTP surface

Routes: `routes/organizations.php`, registered in `bootstrap/app.php`.

### Organizations

| Method | URI | Name | Access |
| --- | --- | --- | --- |
| GET | `/organizations` | `organizations.index` | Authenticated, verified |
| GET | `/organizations/create` | `organizations.create` | Authenticated, verified |
| POST | `/organizations` | `organizations.store` | Authenticated, verified |
| GET | `/organizations/{organization:slug}` | `organizations.show` | Active member |
| GET | `/organizations/{organization:slug}/edit` | `organizations.edit` | Manager |
| PATCH | `/organizations/{organization:slug}` | `organizations.update` | Manager |
| DELETE | `/organizations/{organization:slug}` | `organizations.destroy` | Owner |
| POST | `/organizations/{organization:slug}/switch` | `organizations.switch` | Active member |
| POST | `/organizations/{organization:slug}/transfer-ownership` | `organizations.transfer-ownership` | Owner |
| DELETE | `/organizations/{organization:slug}/leave` | `organizations.leave` | Non-owner member |
| POST | `/organizations/{organization:slug}/invitations` | `organizations.invitations.store` | Manager |
| DELETE | `/organizations/{organization:slug}/invitations/{invitation}` | `organizations.invitations.destroy` | Manager |
| PATCH | `/organizations/{organization:slug}/members/{membership}` | `organizations.members.update` | Owner |
| DELETE | `/organizations/{organization:slug}/members/{membership}` | `organizations.members.destroy` | Manager |

Organizations are resolved by `slug`. Memberships and invitations are resolved
by primary key and explicitly checked against the organization in the URL.

### Invitations

| Method | URI | Name | Access |
| --- | --- | --- | --- |
| GET | `/invitations/{token}` | `invitations.show` | Authenticated |
| POST | `/invitations/{token}` | `invitations.accept` | Authenticated |

The invitation page reports a reason when the token is unknown, revoked,
expired, already accepted, for a deleted organization, or addressed to a
different email.

## Domain actions

All actions live in `app/Actions/Organizations/` and run inside database
transactions where state changes are involved.

| Action | Responsibility |
| --- | --- |
| `CreateOrganization` | Creates an organization plus the owner's active admin membership atomically. |
| `ProvisionPersonalOrganization` | Creates the personal organization for a newly registered user. |
| `UpdateOrganization` | Renames an organization; owner or admin only. Slug is stable. |
| `InviteOrganizationMember` | Validates email and role, blocks duplicates and existing members, hashes the token, creates a 7-day invitation. |
| `AcceptOrganizationInvitation` | Locks the invitation, validates state and email, creates or reactivates the membership, records acceptance. |
| `RevokeOrganizationInvitation` | Revokes a pending invitation; owner or admin only. |
| `ChangeOrganizationMemberRole` | Owner-only role change; protects the owner membership. |
| `RemoveOrganizationMember` | Removes a member; admins cannot remove admins or the owner. |
| `LeaveOrganization` | Removes the caller's membership; the owner must transfer or delete first. |
| `TransferOrganizationOwnership` | Moves `owner_id` to an active member and leaves the old owner as an active admin. |
| `DeleteOrganization` | Soft-deletes the organization and revokes pending invitations while retaining memberships. |

Requests are validated through form requests in
`app/Http/Requests/Organizations/`, including `Rule::enum(OrganizationRole::class)`.

## Registration and account deletion

### Registration

`App\Actions\Fortify\CreateNewUser`:

1. Normalizes the email.
2. Validates the profile and password rules.
3. Opens a database transaction.
4. Creates the user.
5. Provisions `"{name}'s Organization"` with an active `admin` owner membership.
6. Commits, or rolls the whole registration back if provisioning fails.

### Account deletion

`App\Http\Controllers\Settings\ProfileController@destroy`:

- Refuses deletion while the user owns any organization, including trashed ones.
- Revokes Sanctum tokens before deleting the user inside a transaction.
- Relies on cascading foreign keys to remove memberships and sent invitations.

## Frontend

Types: `resources/js/types/organization.ts`, exported from
`resources/js/types/index.ts` and referenced by `global.d.ts`.

Pages under `resources/js/pages/organizations/`:

- `Index.vue` — organizations the user belongs to, member counts, active switch.
- `Create.vue` — create an organization.
- `Edit.vue` — rename an organization.
- `Show.vue` — members, roles, invitations, ownership transfer, leave/delete.
- `Invitations/Show.vue` — invitation details and acceptance.

Shared Inertia props from `HandleInertiaRequests`:

- `auth`
- `organizations` — active organizations for the current user
- `currentOrganization`
- `status`
- `invitationUrl`

A sidebar link to `/organizations` was added to `AppSidebar.vue`.

## Spatie permission deferral

`spatie/laravel-permission` remains installed but is **not used** for
organization roles:

- Organization authorization is implemented with Laravel policies and actions.
- No global organization roles or permissions are created.
- A corrective migration was evaluated and deliberately deferred; the package
  tables remain empty.

## Testing

Step 2 tests live in:

- `tests/Feature/Organizations/OrganizationAccessTest.php`
- `tests/Feature/Organizations/OrganizationCreationTest.php`
- `tests/Feature/Organizations/OrganizationDeletionTest.php`
- `tests/Feature/Organizations/OrganizationInvitationTest.php`
- `tests/Feature/Organizations/OrganizationOwnershipTest.php`
- `tests/Feature/Organizations/OrganizationSlugTest.php`
- `tests/Feature/Organizations/RegistrationOrganizationTest.php`
- `tests/Feature/Settings/AccountDeletionTest.php`
- `tests/Unit/EmailNormalizerTest.php`

Coverage includes creation and slug generation, authorization boundaries,
cross-organization isolation, role changes, member removal, leaving, ownership
transfer, invitation lifecycle, token hashing, invitation notification
dispatch, soft delete behavior, registration provisioning and rollback, and
account-deletion safeguards.

### Verification results

- `php artisan test` — **105 passed**, 504 assertions.
- `vendor/bin/pint --test` — passed.
- `vendor/bin/phpstan analyse` (level 7) — passed, 0 errors.
- `npm run build` — passed.
- `npm run types:check` — passed.
- `composer ci:check` — **not green**. `npm run check` reports formatting
  differences in 40 files. The base tree is not formatter-clean, and most of the
  flagged files are inherited files untouched by Step 2. The formatter was not
  run with `--fix` because that would sweep unrelated files into this scoped
  commit. The five checks above pass.

## Out of scope

- Organization API endpoints.
- Global/application roles via Spatie.
- Billing, plans and subscription features.
- Hard deletion or restore UI for organizations.
- Email verification or delivery configuration changes.
