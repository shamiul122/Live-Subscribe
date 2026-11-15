<?php

namespace Database\Factories;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\Subscription;
use App\Support\Billing\LicenseKey;
use Database\Factories\Concerns\ResolvesSubscriptionTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    use ResolvesSubscriptionTenant;

    protected $model = License::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $raw = LicenseKey::generate();

        return [
            'key_prefix' => LicenseKey::prefix($raw),
            'key_last_four' => LicenseKey::lastFour($raw),
            'label' => fake()->word(),
            'status' => LicenseStatus::Active,
            'activated_at' => null,
            'expires_at' => now()->addMonthNoOverflow(),
            'last_validated_at' => null,
            'suspended_at' => null,
            'revoked_at' => null,
            'revoked_by_id' => null,
            'created_by_id' => null,
        ];
    }

    /**
     * key_hash is guarded and the tenant pair is resolved explicitly.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): License
    {
        $hash = $attributes['key_hash'] ?? LicenseKey::hash(LicenseKey::generate());
        unset($attributes['key_hash']);

        [$subscriptionId, $organizationId] = $this->resolveSubscriptionTenant($attributes);

        $license = parent::newModel($attributes);
        $license->key_hash = $hash;
        $license->subscription_id = $subscriptionId;
        $license->organization_id = $organizationId;

        return $license;
    }

    public function forSubscription(Subscription $subscription): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $subscription->organization_id,
            'subscription_id' => $subscription->id,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'status' => LicenseStatus::Revoked,
            'revoked_at' => now(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => LicenseStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => LicenseStatus::Expired,
            'expires_at' => now()->subDay(),
        ]);
    }
}
