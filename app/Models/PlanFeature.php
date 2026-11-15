<?php

namespace App\Models;

use App\Enums\Feature;
use App\Enums\FeatureType;
use Database\Factories\PlanFeatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $plan_id
 * @property Feature $feature
 * @property FeatureType $feature_type
 * @property int|null $limit
 */
#[Fillable(['plan_id', 'feature', 'feature_type', 'limit'])]
class PlanFeature extends Model
{
    /** @use HasFactory<PlanFeatureFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'feature' => Feature::class,
            'feature_type' => FeatureType::class,
            'limit' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isUnlimited(): bool
    {
        return $this->limit === null;
    }

    public function isUnavailable(): bool
    {
        return $this->limit === 0;
    }
}
