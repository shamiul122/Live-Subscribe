<?php

namespace Tests\Feature\Organizations;

use App\Actions\Organizations\ProvisionPersonalOrganization;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class RegistrationOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_provisions_a_personal_organization_and_owner_membership(): void
    {
        $response = $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'Jane.Doe@Example.COM',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors();

        $user = User::firstOrFail();

        $this->assertSame('jane.doe@example.com', $user->email);
        $this->assertAuthenticatedAs($user);

        $organization = Organization::firstOrFail();

        $this->assertSame($user->id, $organization->owner_id);
        $this->assertSame("Jane Doe's Organization", $organization->name);
        $this->assertSame('jane-does-organization', $organization->slug);

        $membership = $organization->memberships()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(OrganizationRole::Admin, $membership->role);
        $this->assertSame(OrganizationMembershipStatus::Active, $membership->status);
        $this->assertNotNull($membership->joined_at);
    }

    public function test_registration_rolls_back_the_user_when_provisioning_fails(): void
    {
        $this->mock(ProvisionPersonalOrganization::class, function (MockInterface $mock): void {
            $mock->shouldReceive('handle')->andThrow(new RuntimeException('Provisioning failed.'));
        });

        $response = $this->post('/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(500);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('organizations', 0);
        $this->assertDatabaseCount('organization_memberships', 0);
        $this->assertGuest();
    }
}
