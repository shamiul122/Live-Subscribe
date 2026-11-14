<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrganizations;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use CreatesOrganizations, RefreshDatabase;

    public function test_a_user_who_owns_an_organization_cannot_delete_their_account(): void
    {
        $user = User::factory()->create();
        $this->createOrganization($user);

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_a_user_who_owns_a_soft_deleted_organization_cannot_delete_their_account(): void
    {
        $user = User::factory()->create();
        $organization = $this->createOrganization($user);
        $organization->delete();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_a_non_owner_member_can_delete_their_account_and_tokens_are_revoked(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        $membership = $this->addMember($organization, $member);

        $member->createToken('test-token');

        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $member->id]);

        $this->actingAs($member)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $member->id]);
        $this->assertDatabaseMissing('organization_memberships', ['id' => $membership->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $member->id]);
        $this->assertDatabaseHas('organizations', ['id' => $organization->id]);
    }

    public function test_the_correct_password_is_required_to_delete_an_account(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        $this->addMember($organization, $member);

        $this->actingAs($member)
            ->delete(route('profile.destroy'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('users', ['id' => $member->id]);
    }
}
