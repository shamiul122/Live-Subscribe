<?php

namespace App\Models;

use App\Enums\ConnectedAccountStatus;
use App\Enums\SocialProvider;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Database\Factories\AccountConnectionEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only lifecycle history for connected social accounts. Never stores
 * tokens or secrets; only metadata and status transitions.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $connected_account_id
 * @property string $type
 * @property string|null $status
 * @property array<string, mixed>|null $data
 * @property Carbon|null $created_at
 */
#[Fillable([
    'organization_id',
    'connected_account_id',
    'type',
    'status',
    'data',
    'created_at',
    'actor_id',
])]
class AccountConnectionEvent extends Model
{
    /** @use HasFactory<AccountConnectionEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'type' => 'string',
            'status' => ConnectedAccountStatus::class,
            'data' => 'array',
            'created_at' => 'datetime',
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
     * @return BelongsTo<ConnectedAccount, $this>
     */
    public function connectedAccount(): BelongsTo
    {
        return $this->belongsTo(ConnectedAccount::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
