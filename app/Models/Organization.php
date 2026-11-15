<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use App\Enums\SubscriptionStatus;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'slug'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Ownership is never mass assignable. It is only changed by the
     * TransferOrganizationOwnership action.
     */
    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Every membership, including inactive records.
     *
     * @return HasMany<OrganizationMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /**
     * Active memberships only.
     *
     * @return HasMany<OrganizationMembership, $this>
     */
    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', 'active');
    }

    /**
     * Active members of the organization.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_memberships')
            ->withPivot(['id', 'role', 'status', 'joined_at'])
            ->withTimestamps()
            ->wherePivot('status', 'active');
    }

    /**
     * @return HasMany<OrganizationInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /**
     * Invitations that have not been accepted or revoked yet.
     *
     * @return HasMany<OrganizationInvitation, $this>
     */
    public function pendingInvitations(): HasMany
    {
        return $this->invitations()
            ->whereNull('accepted_at')
            ->whereNull('revoked_at');
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function hasMember(User $user): bool
    {
        return $this->activeMembershipFor($user) !== null;
    }

    public function activeMembershipFor(User $user): ?OrganizationMembership
    {
        return $this->memberships()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
    }

    public function roleFor(User $user): ?OrganizationRole
    {
        if ($this->isOwnedBy($user)) {
            return OrganizationRole::Admin;
        }

        return $this->activeMembershipFor($user)?->role;
    }

    /**
     * Every subscription, including expired ones.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * The single non-terminal subscription, if one exists.
     *
     * @return HasOne<Subscription, $this>
     */
    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', '!=', SubscriptionStatus::Expired->value)
            ->latestOfMany();
    }

    /**
     * @return HasMany<License, $this>
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    /**
     * @return HasMany<ConnectedAccount, $this>
     */
    public function connectedAccounts(): HasMany
    {
        return $this->hasMany(ConnectedAccount::class);
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * @return HasMany<CampaignRecipient, $this>
     */
    public function campaignRecipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<UsageRecord, $this>
     */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    /**
     * @return HasMany<SubscriptionEvent, $this>
     */
    public function subscriptionEvents(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class);
    }

    /**
     * Whether this organization has ever used a trial. Retained history means
     * an expired trial still counts.
     */
    public function hasUsedTrial(): bool
    {
        return $this->subscriptions()->whereNotNull('trial_ends_at')->exists();
    }

    /**
     * Resolve route model binding by slug instead of the primary key.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
