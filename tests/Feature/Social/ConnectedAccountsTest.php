<?php

namespace Tests\Feature\Social;

use App\Enums\ConnectedAccountStatus;
use App\Enums\EntitlementReason;
use App\Enums\Feature;
use App\Enums\SocialProvider;
use App\Models\ConnectedAccount;
use App\Models\Organization;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use App\Services\Social\SocialAccountManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBilling;
use Tests\Concerns\CreatesOrganizations;
use Tests\TestCase;

class ConnectedAccountsTest extends TestCase
{
    use CreatesBilling, CreatesOrganizations, RefreshDatabase;

    public function test_owner_can_list_connected_accounts(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $account = ConnectedAccount::query()->create([
            'organization_id' => $organization->id,
            'provider' => SocialProvider::Facebook->value,
            'provider_account_id' => 'fb-12345',
            'account_name' => 'Acme Page',
            'account_username' => 'acme',
            'account_type' => 'page',
            'status' => ConnectedAccountStatus::Active,
            'token_expires_at' => now()->addDay(),
            'last_synced_at' => now(),
        ]);

        $accounts = app(SocialAccountManager::class)->list($organization);

        $this->assertCount(1, $accounts);
        $this->assertSame($account->id, $accounts[0]['id']);
        $this->assertSame('facebook', $accounts[0]['provider']);
    }

    public function test_connected_accounts_are_scoped_to_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $otherOrganization = $this->createOrganization($owner, 'Other');

        ConnectedAccount::query()->create([
            'organization_id' => $organization->id,
            'provider' => SocialProvider::Facebook->value,
            'provider_account_id' => 'fb-1',
            'account_name' => 'Acme Page',
            'status' => ConnectedAccountStatus::Active,
        ]);

        ConnectedAccount::query()->create([
            'organization_id' => $otherOrganization->id,
            'provider' => SocialProvider::Facebook->value,
            'provider_account_id' => 'fb-2',
            'account_name' => 'Other Page',
            'status' => ConnectedAccountStatus::Active,
        ]);

        $accounts = app(SocialAccountManager::class)->list($organization);

        $this->assertCount(1, $accounts);
        $this->assertSame('fb-1', $accounts[0]['provider_account_id']);
    }

    public function test_member_cannot_start_social_connection(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        $this->addMember($organization, $member, \App\Enums\OrganizationRole::Member);

        $result = app(SocialAccountManager::class)->start($member, $organization, SocialProvider::Facebook);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('permission_denied', $result['reason']);
    }

    public function test_start_returns_provider_not_configured_when_credentials_missing(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $plan = $this->createPlan(['trial_days' => 14], [
            Feature::ConnectedAccounts->value => 3,
        ]);
        $subscription = $this->subscribe($organization, $plan);

        $result = app(SocialAccountManager::class)->start($owner, $organization, SocialProvider::Facebook);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('provider_not_configured', $result['reason']);
    }

    public function test_oauth_state_is_one_time_use(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $plan = $this->createPlan(['trial_days' => 14], [
            Feature::ConnectedAccounts->value => 3,
        ]);
        $subscription = $this->subscribe($organization, $plan);

        $state = bin2hex(random_bytes(32));

        \Illuminate\Support\Facades\Cache::put(
            'social.oauth.states.'.hash('sha256', $state),
            [
                'organization_id' => $organization->id,
                'provider' => SocialProvider::Facebook->value,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ],
            now()->addMinutes(10)->seconds(),
        );

        $first = app(SocialAccountManager::class)->complete(
            $owner,
            $organization,
            SocialProvider::Facebook,
            $state,
            'auth-code',
            'http://localhost/callback',
        );

        $this->assertSame('failed', $first['status']);

        $second = app(SocialAccountManager::class)->complete(
            $owner,
            $organization,
            SocialProvider::Facebook,
            $state,
            'auth-code-2',
            'http://localhost/callback',
        );

        $this->assertSame('failed', $second['status']);
        $this->assertSame('invalid_state', $second['reason']);
    }

    public function test_token_is_encrypted_at_rest_and_not_in_summary(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $account = ConnectedAccount::query()->create([
            'organization_id' => $organization->id,
            'provider' => SocialProvider::Facebook->value,
            'provider_account_id' => 'fb-enc-1',
            'account_name' => 'Acme Page',
            'status' => ConnectedAccountStatus::Active,
        ]);

        $raw = 'ls_live_secret_value_123';

        $account->access_token = $raw;
        $account->refresh_token = $raw;
        $account->save();

        $fresh = ConnectedAccount::query()->findOrFail($account->id);

        $this->assertNotSame($raw, $fresh->getRawOriginal('access_token'));
        $this->assertSame($raw, $fresh->access_token);

        $summary = app(SocialAccountManager::class)->summary($fresh);

        $this->assertArrayNotHasKey('access_token', $summary);
        $this->assertArrayNotHasKey('refresh_token', $summary);
    }

    public function test_duplicate_provider_account_is_prevented_by_unique_constraint(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $attributes = [
            'organization_id' => $organization->id,
            'provider' => SocialProvider::Facebook->value,
            'provider_account_id' => 'fb-dup-1',
            'account_name' => 'Acme Page',
            'status' => ConnectedAccountStatus::Active,
        ];

        ConnectedAccount::query()->create($attributes);

        $this->expectException(\Illuminate\Database\QueryException::class);

        ConnectedAccount::query()->create($attributes);
    }

    public function test_connected_account_limit_enforcement_via_entitlement_service(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $plan = $this->createPlan(['trial_days' => 14], [
            Feature::ConnectedAccounts->value => 2,
        ]);
        $subscription = $this->subscribe($organization, $plan);

        $entitlements = app(EntitlementService::class);

        $initialDecision = $entitlements->check($owner, $organization, Feature::ConnectedAccounts, permission: null);
        $this->assertTrue($initialDecision->isAllowed());

        ConnectedAccount::query()->create([
            'organization_id' => $organization->id,
            'provider' => SocialProvider::Facebook->value,
            'provider_account_id' => 'fb-limit-1',
            'account_name' => 'Page One',
            'status' => ConnectedAccountStatus::Active,
        ]);

        $secondDecision = $entitlements->check($owner, $organization, Feature::ConnectedAccounts, permission: null);
        $this->assertTrue($secondDecision->isAllowed());

        ConnectedAccount::query()->create([
            'organization_id' => $organization->id,
            'provider' => SocialProvider::Facebook->value,
            'provider_account_id' => 'fb-limit-2',
            'account_name' => 'Page Two',
            'status' => ConnectedAccountStatus::Active,
        ]);

        $finalDecision = $entitlements->check($owner, $organization, Feature::ConnectedAccounts, permission: null);

        $this->assertFalse($finalDecision->isAllowed());
        $this->assertSame(EntitlementReason::ResourceLimitReached, $finalDecision->reason);
    }

    public function test_disconnect_records_lifecycle_event(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $account = ConnectedAccount::query()->create([
            'organization_id' => $organization->id,
            'provider' => SocialProvider::Facebook->value,
            'provider_account_id' => 'fb-disc-1',
            'account_name' => 'Acme Page',
            'status' => ConnectedAccountStatus::Active,
        ]);

        $result = app(SocialAccountManager::class)->disconnect($owner, $organization, $account);

        $this->assertSame('disconnected', $result['status']);

        $account->refresh();

        $this->assertSame(ConnectedAccountStatus::Revoked, $account->status);

        $this->assertDatabaseHas('account_connection_events', [
            'organization_id' => $organization->id,
            'connected_account_id' => $account->id,
            'type' => 'account_disconnected',
        ]);
    }
}
