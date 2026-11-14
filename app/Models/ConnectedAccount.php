<?php

namespace App\Models;

use App\Enums\ConnectedAccountStatus;
use App\Enums\SocialProvider;
use Database\Factories\ConnectedAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A connected external communication/social account for an organization.
 *
 * @property int $id
 * @property int $organization_id
 * @property SocialProvider $provider
 * @property string $provider_account_id
 * @property string $account_name
 * @property string|null $account_username
 * @property string|null $account_type
 * @property ConnectedAccountStatus $status
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property int|null $connected_by_id
 * @property Carbon|null $last_synced_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'organization_id',
    'provider',
    'provider_account_id',
    'account_name',
    'account_username',
    'account_type',
    'status',
    'access_token',
    'refresh_token',
    'token_expires_at',
    'connected_by_id',
    'last_synced_at',
    'metadata',
])]
class ConnectedAccount extends Model
{
    /** @use HasFactory<ConnectedAccountFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The raw tokens live in memory only during an exchange; persisted tokens
     * are stored through the encrypted casts.
     */
    public ?string $plainAccessToken = null;

    public ?string $plainRefreshToken = null;

    protected function casts(): array
    {
        return [
            'provider' => SocialProvider::class,
            'status' => ConnectedAccountStatus::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_id');
    }

    /**
     * @return HasMany<AccountConnectionEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AccountConnectionEvent::class);
    }

    public function isActive(): bool
    {
        return $this->status === ConnectedAccountStatus::Active;
    }

    public function isUsableAt(Carbon $at): bool
    {
        return $this->isActive() && ($this->token_expires_at === null || $at->lessThan($this->token_expires_at));
    }

    /**
     * Stable masked representation for display. Tokens are never included.
     */
    public function maskedAccount(): string
    {
        return $this->account_name;
    }
}
