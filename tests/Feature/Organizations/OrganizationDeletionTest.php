<?php

namespace Tests\Feature\Organizations;

use App\Enums\OrganizationRole;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrganizations;
use Tests\TestCase;

class OrganizationDeletionTest extends TestCase
{
    use CreatesOrganizations, RefreshDatabase;

    public function test_the_owner_can_soft_delete_the_organization(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $member = User::factory()->create();
        $this->addMember($organization, $member);

        $this->actingAs($owner)
            ->delete(route('organizations.destroy', $organization))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('organizations.index'));

        $this->assertSoftDeleted('organizations', ['id' => $organization->id]);

        // Memberships are retained so a restored organization keeps its history.
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_deleting_an_organization_revokes_pending_invitations(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $pending = OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
            'invited_by_id' => $owner->id,
            'email' => 'pending@example.com',
        ]);

        $accepted = OrganizationInvitation::factory()->accepted()->create([
            'organization_id' => $organization->id,
            'invited_by_id' => $owner->id,
            'email' => 'accepted@example.com',
        ]);

        $this->actingAs($owner)
            ->delete(route('organizations.destroy', $organization))
            ->assertRedirect(route('organizations.index'));

        $this->assertNotNull($pending->fresh()->revoked_at);
        $this->assertNull($accepted->fresh()->revoked_at);
    }

    public function test_admins_and_members_cannot_delete_the_organization(): void
    {
        $organization = $this->createOrganization(User::factory()->create());

        $admin = User::factory()->create();
        $this->addMember($organization, $admin, OrganizationRole::Admin);

        $member = User::factory()->create();
        $this->addMember($organization, $member);

        $this->actingAs($admin)
            ->delete(route('organizations.destroy', $organization))
            ->assertForbidden();

        $this->actingAs($member)
            ->delete(route('organizations.destroy', $organization))
            ->assertForbidden();

        $this->assertNull($organization->fresh()->deleted_at);
    }

    public function test_deleted_organizations_are_not_reachable_by_slug(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)->delete(route('organizations.destroy', $organization));

        $this->actingAs($owner)
            ->get('/organizations/'.$organization->slug)
            ->assertNotFound();
    }

    public function test_the_owner_can_rename_the_organization_without_changing_the_slug(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'Original Name');

        $this->actingAs($owner)
            ->patch(route('organizations.update', $organization), ['name' => 'Renamed'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('organizations.show', $organization));

        $organization->refresh();

        $this->assertSame('Renamed', $organization->name);
        $this->assertSame('original-name', $organization->slug);
    }

    public function test_admins_can_rename_the_organization(): void
    {
        $organization = $this->createOrganization(User::factory()->create());
        $admin = User::factory()->create();
        $this->addMember($organization, $admin, OrganizationRole::Admin);

        $this->actingAs($admin)
            ->patch(route('organizations.update', $organization), ['name' => 'Renamed by admin'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed by admin', $organization->fresh()->name);
    }

    public function test_the_organization_name_is_required(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)
            ->patch(route('organizations.update', $organization), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertSame('Acme', $organization->fresh()->name);
    }

    public function test_only_managers_can_open_the_edit_page(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);

        $this->actingAs($owner)
            ->get(route('organizations.edit', $organization))
            ->assertOk();

        $admin = User::factory()->create();
        $this->addMember($organization, $admin, OrganizationRole::Admin);

        $this->actingAs($admin)
            ->get(route('organizations.edit', $organization))
            ->assertOk();

        $member = User::factory()->create();
        $this->addMember($organization, $member);

        $this->actingAs($member)
            ->get(route('organizations.edit', $organization))
            ->assertForbidden();
    }

    public function test_the_index_excludes_deleted_organizations(): void
    {
        $owner = User::factory()->create();
        $active = $this->createOrganization($owner, 'Active');
        $deleted = $this->createOrganization($owner, 'Deleted');

        $deleted->delete();

        $this->actingAs($owner)
            ->get(route('organizations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('organizations', 1)
                ->where('organizations.0.id', $active->id),
            );
    }
}
