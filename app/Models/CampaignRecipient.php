<?php

namespace App\Models;

use App\Enums\CampaignRecipientStatus;
use App\Enums\MessageDeliveryStatus;
use Database\Factories\CampaignRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single contact intended to receive a campaign.
 *
 * Recipients are materialised from the campaign audience snapshot. Their status
 * stays `pending` in this step; only a future delivery integration may advance
 * it.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $campaign_id
 * @property int|null $contact_id
 * @property CampaignRecipientStatus $status
 * @property MessageDeliveryStatus|null $delivery_status
 * @property string|null $error_message
 * @property Carbon|null $queued_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon|null $failed_at
 * @property string|null $provider_message_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'campaign_id',
    'contact_id',
    'status',
    'delivery_status',
    'error_message',
    'queued_at',
    'sent_at',
    'delivered_at',
    'read_at',
    'failed_at',
    'provider_message_id',
    'metadata',
])]
class CampaignRecipient extends Model
{
    /** @use HasFactory<CampaignRecipientFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'campaign_id' => 'integer',
            'contact_id' => 'integer',
            'status' => CampaignRecipientStatus::class,
            'delivery_status' => MessageDeliveryStatus::class,
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
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
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
