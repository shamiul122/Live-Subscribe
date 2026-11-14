<?php

namespace Tests\Feature\Organizations;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrganizations;
use Tests\TestCase;

class OrganizationOwnershipTest extends TestCase
{
    use CreatesOrganizations, RefreshDatabase;

    public function test_the_owner_can_change_a_members_role(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        $membership = $this->addMember($organization, $member);

        $this->actingAs($owner)
            ->patch(route('organizations.members.update', [$organization, $membership]), [
                'role' => OrganizationRole::Admin->value,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('organizations.show', $organization));

        $this->assertSame(OrganizationRole::Admin, $membership->fresh()->role);

        $this->actingAs($owner)
            ->patch(route('organizations.members.update', [$organization, $membership]), [
                'role' => OrganizationRole::Member->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(OrganizationRole::Member, $membership->fresh()->role);
    }

    public function test_admins_cannot_change_roles(): void
    {
        $organization = $this->createOrganization(User::factory()->create());
        $admin = User::factory()->create();
        $this->addMember($organization, $admin, OrganizationRole::Admin);

        $member = User::factory()->create();
        $membership = $this->addMember($organization, $member);

        $this->actingAs($admin)
            ->patch(route('organizations.members.update', [$organization, $membership]), [
                'role' => OrganizationRole::Admin->value,
            ])
            ->assertForbidden();

        $this->assertSame(OrganizationRole::Member, $membership->fresh()->role);
    }

    public function test_the_owner_role_cannot_be_changed(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $ownerMembership = $organization->memberships()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($owner)
            ->patch(route('organizations.members.update', [$organization, $ownerMembership]), [
                'role' => OrganizationRole::Member->value,
            ])
            ->assertSessionHasErrors('membership');

        $this->assertSame(OrganizationRole::Admin, $ownerMembership->fresh()->role);
    }

    public function test_memberships_from_another_organization_cannot_be_changed(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $otherOrganization = $this->createOrganization($owner, 'Other');
        $otherMember = User::factory()->create();
        $otherMembership = $this->addMember($otherOrganization, $otherMember);

        $this->actingAs($owner)
            ->patch(route('organizations.members.update', [$organization, $otherMembership]), [
                'role' => OrganizationRole::Admin->value,
            ])
            ->assertNotFound();
    }

    public function test_an_admin_can_remove_a_member(): void
    {
        $organization = $this->createOrganization(User::factory()->create());
        $admin = User::factory()->create();
        $this->addMember($organization, $admin, OrganizationRole::Admin);

        $member = User::factory()->create();
        $membership = $this->addMember($organization, $member);

        $this->actingAs($admin)
            ->delete(route('organizations.members.destroy', [$organization, $membership]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('organizations.show', $organization));

        $this->assertDatabaseMissing('organization_memberships', ['id' => $membership->id]);
    }

    public function test_an_admin_cannot_remove_another_admin(): void
    {
        $organization = $this->createOrganization(User::factory()->create());
        $admin = User::factory()->create();
        $this->addMember($organization, $admin, OrganizationRole::Admin);

        $otherAdmin = User::factory()->create();
        $otherMembership = $this->addMember($organization, $otherAdmin, OrganizationRole::Admin);

        $this->actingAs($admin)
            ->delete(route('organizations.members.destroy', [$organization, $otherMembership]))
            ->assertForbidden();

        $this->assertDatabaseHas('organization_memberships', ['id' => $otherMembership->id]);
    }

    public function test_the_owner_can_remove_an_admin(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $admin = User::factory()->create();
        $membership = $this->addMember($organization, $admin, OrganizationRole::Admin);

        $this->actingAs($owner)
            ->delete(route('organizations.members.destroy', [$organization, $membership]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('organization_memberships', ['id' => $membership->id]);
    }

    public function test_the_owner_cannot_be_removed(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $ownerMembership = $organization->memberships()->where('user_id', $owner->id)->firstOrFail();

        $this->actingAs($owner)
            ->delete(route('organizations.members.destroy', [$organization, $ownerMembership]))
            ->assertSessionHasErrors('membership');

        $this->assertDatabaseHas('organization_memberships', ['id' => $ownerMembership->id]);
    }

    public function test_members_cannot_remove_other_members(): void
    {
        $organization = $this->createOrganization(User::factory()->create());
        $member = User::factory()->create();
        $this->addMember($organization, $member);
        $other = User::factory()->create();
        $otherMembership = $this->addMember($organization, $other);

        $this->actingAs($member)
            ->delete(route('organizations.members.destroy', [$organization, $otherMembership]))
            ->assertForbidden();

        $this->assertDatabaseHas('organization_memberships', ['id' => $otherMembership->id]);
    }

    public function test_a_member_can_leave_an_organization(): void
    {
        $organization = $this->createOrganization(User::factory()->create());
        $member = User::factory()->create();
        $this->addMember($organization, $member);

        $this->actingAs($member)
            ->delete(route('organizations.leave', $organization))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('organizations.index'));

        $this->assertDatabaseMissing('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_the_owner_cannot_leave_the_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)
            ->delete(route('organizations.leave', $organization))
            ->assertForbidden();

        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_ownership_can_be_transferred_to_an_active_member(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $newOwner = User::factory()->create();
        $newOwnerMembership = $this->addMember($organization, $newOwner);

        $this->actingAs($owner)
            ->post(route('organizations.transfer-ownership', $organization), [
                'email' => $newOwner->email,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('organizations.show', $organization));

        $organization->refresh();

        $this->assertSame($newOwner->id, $organization->owner_id);
        $this->assertSame(OrganizationRole::Admin, $newOwnerMembership->fresh()->role);

        $oldOwnerMembership = $organization->memberships()
            ->where('user_id', $owner->id)
            ->firstOrFail();

        $this->assertSame(OrganizationRole::Admin, $oldOwnerMembership->role);
        $this->assertSame(OrganizationMembershipStatus::Active, $oldOwnerMembership->status);
    }

    public function test_ownership_cannot_be_transferred_to_a_non_member(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $outsider = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('organizations.transfer-ownership', $organization), [
                'email' => $outsider->email,
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame($owner->id, $organization->fresh()->owner_id);
    }

    public function test_ownership_cannot_be_transferred_to_an_inactive_member(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $inactive = User::factory()->create();
        $this->addMember(
            $organization,
            $inactive,
            OrganizationRole::Member,
            OrganizationMembershipStatus::Inactive,
        );

        $this->actingAs($owner)
            ->post(route('organizations.transfer-ownership', $organization), [
                'email' => $inactive->email,
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame($owner->id, $organization->fresh()->owner_id);
    }

    public function test_ownership_cannot_be_transferred_to_yourself(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)
            ->post(route('organizations.transfer-ownership', $organization), [
                'email' => $owner->email,
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame($owner->id, $organization->fresh()->owner_id);
    }

    public function test_non_owners_cannot_transfer_ownership(): void
    {
        $organization = $this->createOrganization(User::factory()->create());
        $admin = User::factory()->create();
        $this->addMember($organization, $admin, OrganizationRole::Admin);

        $target = User::factory()->create();
        $this->addMember($organization, $target);

        $this->actingAs($admin)
            ->post(route('organizations.transfer-ownership', $organization), [
                'email' => $target->email,
            ])
            ->assertForbidden();
    }

    public function test_the_organization_policy_matches_the_documented_matrix(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $admin = User::factory()->create();
        $this->addMember($organization, $admin, OrganizationRole::Admin);
        $member = User::factory()->create();
        $this->addMember($organization, $member);
        $outsider = User::factory()->create();

        $this->assertTrue($owner->can('view', $organization));
        $this->assertTrue($owner->can('update', $organization));
        $this->assertTrue($owner->can('delete', $organization));
        $this->assertTrue($owner->can('transferOwnership', $organization));
        $this->assertTrue($owner->can('inviteMember', $organization));
        $this->assertTrue($owner->can('updateMemberRole', $organization));
        $this->assertTrue($owner->can('removeMember', $organization));
        $this->assertFalse($owner->can('leave', $organization));

        $this->assertTrue($admin->can('view', $organization));
        $this->assertTrue($admin->can('update', $organization));
        $this->assertFalse($admin->can('delete', $organization));
        $this->assertFalse($admin->can('transferOwnership', $organization));
        $this->assertTrue($admin->can('inviteMember', $organization));
        $this->assertFalse($admin->can('updateMemberRole', $organization));
        $this->assertTrue($admin->can('removeMember', $organization));
        $this->assertTrue($admin->can('leave', $organization));

        $this->assertTrue($member->can('view', $organization));
        $this->assertFalse($member->can('update', $organization));
        $this->assertFalse($member->can('delete', $organization));
        $this->assertFalse($member->can('inviteMember', $organization));
        $this->assertFalse($member->can('updateMemberRole', $organization));
        $this->assertFalse($member->can('removeMember', $organization));
        $this->assertTrue($member->can('leave', $organization));

        $this->assertFalse($outsider->can('view', $organization));
        $this->assertFalse($outsider->can('update', $organization));
        $this->assertFalse($outsider->can('delete', $organization));
        $this->assertFalse($outsider->can('inviteMember', $organization));
        $this->assertFalse($outsider->can('leave', $organization));
    }
}
