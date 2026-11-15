<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A customer or contact belonging to an organization.
 *
 * Emails and phone numbers are intentionally not globally unique; every
 * organization keeps its own contact records.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $avatar
 * @property string|null $notes
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'organization_id',
    'name',
    'email',
    'phone',
    'avatar',
    'notes',
    'metadata',
])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'email' => 'string',
            'phone' => 'string',
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
     * Conversations that reference this contact.
     *
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Campaign recipients that reference this contact.
     *
     * @return HasMany<CampaignRecipient, $this>
     */
    public function campaignRecipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }
}
