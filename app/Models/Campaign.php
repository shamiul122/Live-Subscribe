<?php

namespace App\Models;

use App\Enums\CampaignAudienceType;
use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An organization-scoped marketing campaign.
 *
 * A campaign only describes intent. Preparing recipients is explicit and no
 * message is ever delivered by this model or its services.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $connected_account_id
 * @property int|null $created_by_id
 * @property string $name
 * @property string|null $description
 * @property CampaignType $type
 * @property string|null $channel
 * @property CampaignStatus $status
 * @property string|null $message_content
 * @property CampaignAudienceType $audience_type
 * @property array<string, mixed>|null $audience_config
 * @property int $audience_size
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read int|null $recipients_count
 */
#[Fillable([
    'organization_id',
    'connected_account_id',
    'created_by_id',
    'name',
    'description',
    'type',
    'channel',
    'status',
    'message_content',
    'audience_type',
    'audience_config',
    'audience_size',
    'scheduled_at',
    'started_at',
    'completed_at',
    'cancelled_at',
    'metadata',
])]
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'connected_account_id' => 'integer',
            'created_by_id' => 'integer',
            'type' => CampaignType::class,
            'status' => CampaignStatus::class,
            'audience_type' => CampaignAudienceType::class,
            'audience_config' => 'array',
            'audience_size' => 'integer',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<ConnectedAccount, $this>
     */
    public function connectedAccount(): BelongsTo
    {
        return $this->belongsTo(ConnectedAccount::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * The single active audience snapshot for this campaign.
     *
     * @return HasOne<CampaignAudienceSnapshot, $this>
     */
    public function audienceSnapshot(): HasOne
    {
        return $this->hasOne(CampaignAudienceSnapshot::class);
    }

    /**
     * @return HasMany<CampaignRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function isEditable(): bool
    {
        return $this->status->canEdit();
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }
}
