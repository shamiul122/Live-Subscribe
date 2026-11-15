<?php

namespace App\Models;

use App\Enums\CampaignAudienceType;
use Database\Factories\CampaignAudienceSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A frozen record of the contacts a campaign intends to reach.
 *
 * Editing a contact afterwards never changes the campaign's intended audience:
 * the resolved contact ids are stored on this row.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $campaign_id
 * @property int|null $captured_by_id
 * @property CampaignAudienceType $audience_type
 * @property array<string, mixed>|null $filters
 * @property array<int, int>|null $contact_ids
 * @property int $contact_count
 * @property Carbon|null $captured_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'campaign_id',
    'captured_by_id',
    'audience_type',
    'filters',
    'contact_ids',
    'contact_count',
    'captured_at',
    'metadata',
])]
class CampaignAudienceSnapshot extends Model
{
    /** @use HasFactory<CampaignAudienceSnapshotFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'campaign_id' => 'integer',
            'captured_by_id' => 'integer',
            'audience_type' => CampaignAudienceType::class,
            'filters' => 'array',
            'contact_ids' => 'array',
            'contact_count' => 'integer',
            'captured_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_id');
    }
}
