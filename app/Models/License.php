<?php

namespace App\Models;

use App\Enums\LicenseStatus;
use App\Support\Billing\LicenseKey;
use Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $subscription_id
 * @property string $key_hash
 * @property string $key_prefix
 * @property string $key_last_four
 * @property string|null $label
 * @property LicenseStatus $status
 * @property Carbon|null $activated_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_validated_at
 * @property Carbon|null $suspended_at
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by_id
 * @property int|null $created_by_id
 */
#[Fillable([
    'organization_id',
    'subscription_id',
    'key_prefix',
    'key_last_four',
    'label',
    'status',
    'activated_at',
    'expires_at',
    'last_validated_at',
    'suspended_at',
    'revoked_at',
    'revoked_by_id',
    'created_by_id',
])]
class License extends Model
{
    /** @use HasFactory<LicenseFactory> */
    use HasFactory;

    /**
     * The raw key is held in memory only immediately after issuance or
     * rotation. It is never persisted or serialized.
     */
    public ?string $plainKey = null;

    /**
     * key_hash is deliberately not mass assignable; it is set explicitly by
     * the license actions.
     */
    protected function casts(): array
    {
        return [
            'status' => LicenseStatus::class,
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_validated_at' => 'datetime',
            'suspended_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function isActive(): bool
    {
        return $this->status === LicenseStatus::Active;
    }

    public function isUsableAt(Carbon $at): bool
    {
        return $this->isActive()
            && ($this->expires_at === null || $at->lessThan($this->expires_at));
    }

    public function maskedKey(): string
    {
        return LicenseKey::mask($this->key_prefix, $this->key_last_four);
    }
}
