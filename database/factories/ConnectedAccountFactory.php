<?php

namespace Database\Factories;

use App\Enums\ConnectedAccountStatus;
use App\Enums\SocialProvider;
use App\Models\ConnectedAccount;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ConnectedAccount>
 */
class ConnectedAccountFactory extends Factory
{
    protected $model = ConnectedAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'provider' => SocialProvider::Facebook,
            'provider_account_id' => (string) Str::uuid(),
            'account_name' => fake()->company(),
            'account_username' => fake()->userName(),
            'account_type' => 'profile',
            'status' => ConnectedAccountStatus::Active,
            'access_token' => 'encrypted:'.base64_encode(random_bytes(32)),
            'refresh_token' => 'encrypted:'.base64_encode(random_bytes(32)),
            'token_expires_at' => now()->addDay(),
            'connected_by_id' => User::factory(),
            'last_synced_at' => now(),
            'metadata' => ['external_id' => (string) Str::uuid()],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => ConnectedAccountStatus::Pending]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['status' => ConnectedAccountStatus::Revoked]);
    }
}
