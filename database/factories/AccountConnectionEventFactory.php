<?php

namespace Database\Factories;

use App\Enums\ConnectedAccountStatus;
use App\Models\AccountConnectionEvent;
use App\Models\ConnectedAccount;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountConnectionEvent>
 */
class AccountConnectionEventFactory extends Factory
{
    protected $model = AccountConnectionEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'connected_account_id' => ConnectedAccount::factory(),
            'type' => 'account_connected',
            'status' => ConnectedAccountStatus::Active,
            'data' => ['provider' => 'facebook'],
            'created_at' => now(),
        ];
    }

    public function accountConnected(): static
    {
        return $this->state(fn (): array => ['type' => 'account_connected', 'status' => ConnectedAccountStatus::Active]);
    }

    public function accountDisconnected(): static
    {
        return $this->state(fn (): array => ['type' => 'account_disconnected', 'status' => ConnectedAccountStatus::Revoked]);
    }

    public function tokenRefreshed(): static
    {
        return $this->state(fn (): array => ['type' => 'token_refreshed', 'status' => ConnectedAccountStatus::Active]);
    }

    public function connectionFailed(): static
    {
        return $this->state(fn (): array => ['type' => 'connection_failed', 'status' => ConnectedAccountStatus::Pending]);
    }

    public function connectionExpired(): static
    {
        return $this->state(fn (): array => ['type' => 'connection_expired', 'status' => ConnectedAccountStatus::Revoked]);
    }
}
