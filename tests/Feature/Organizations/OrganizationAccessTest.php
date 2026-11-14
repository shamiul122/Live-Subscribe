<?php

namespace Tests\Feature\Organizations;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesOrganizations;
use Tests\TestCase;

class OrganizationAccessTest extends TestCase
{
    use CreatesOrganizations, RefreshDatabase;

    public function test_guests_are_redirected_from_every_organization_route(): void
    {
        $organization = $this->createOrganization(User::factory()->create());

        $routes = [
            ['get', route('organizations.index')],
            ['get', route('organizations.create')],
            ['post', route('organizations.store')],
            ['get', route('organizations.show', $organization)],
            ['get', route('organizations.edit', $organization)],
            ['patch', route('organizations.update', $organization)],
            ['delete', route('organizations.destroy', $organization)],
            ['delete', route('organizations.leave', $organization)],
        ];

        foreach ($routes as [$method, $uri]) {
            $this->{$method}($uri)->assertRedirect(route('login'));
        }
    }

    public function test_non_members_cannot_view_an_organization(): void
    {
        $organization = $this->createOrganization(User::factory()->create());
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('organizations.show', $organization))
            ->assertForbidden();
    }

    public function test_non_members_cannot_mutate_an_organization(): void
    {
        $organization = $this->createOrganization(User::factory()->create());
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->patch(route('organizations.update', $organization), ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->actingAs($outsider)
            ->delete(route('organizations.destroy', $organization))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('organizations.invitations.store', $organization), [
                'email' => 'invitee@example.com',
                'role' => OrganizationRole::Member->value,
            ])
            ->assertForbidden();

        $this->assertSame('Acme', $organization->fresh()->name);
        $this->assertDatabaseCount('organization_invitations', 0);
    }

    public function test_inactive_members_are_treated_as_non_members(): void
    {
        $organization = $this->createOrganization(User::factory()->create());
        $user = User::factory()->create();

        $this->addMember(
            $organization,
            $user,
            OrganizationRole::Member,
            OrganizationMembershipStatus::Inactive,
        );

        $this->actingAs($user)
            ->get(route('organizations.show', $organization))
            ->assertForbidden();
    }

    public function test_a_member_can_view_the_organization_but_cannot_manage_it(): void
    {
        $organization = $this->createOrganization(User::factory()->create());
        $member = User::factory()->create();

        $this->addMember($organization, $member, OrganizationRole::Member);

        $this->actingAs($member)
            ->get(route('organizations.show', $organization))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('organizations/Show')
                ->where('current_user_role', OrganizationRole::Member->value)
                ->where('permissions.update', false)
                ->where('permissions.delete', false)
                ->where('permissions.invite', false)
                ->where('permissions.update_role', false)
                ->where('permissions.remove_member', false)
                ->where('permissions.transfer_ownership', false)
                ->where('permissions.leave', true),
            );

        $this->actingAs($member)
            ->get(route('organizations.edit', $organization))
            ->assertForbidden();

        $this->actingAs($member)
            ->patch(route('organizations.update', $organization), ['name' => 'Renamed'])
            ->assertForbidden();
    }

    public function test_admins_can_manage_the_organization_but_not_own_it(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner);
        $admin = User::factory()->create();

        $this->addMember($organization, $admin, OrganizationRole::Admin);

        $this->actingAs($admin)
            ->get(route('organizations.show', $organization))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('current_user_role', OrganizationRole::Admin->value)
                ->where('permissions.update', true)
                ->where('permissions.invite', true)
                ->where('permissions.remove_member', true)
                ->where('permissions.delete', false)
                ->where('permissions.transfer_ownership', false)
                ->where('permissions.update_role', false),
            );

        $this->actingAs($admin)
            ->patch(route('organizations.update', $organization), ['name' => 'Renamed by admin'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('organizations.show', $organization));

        $this->assertSame('Renamed by admin', $organization->fresh()->name);
    }

    public function test_a_user_can_switch_the_active_organization_only_if_they_belong_to_it(): void
    {
        $organization = $this->createOrganization(User::factory()->create());
        $member = User::factory()->create();

        $this->addMember($organization, $member);

        $this->actingAs($member)
            ->post(route('organizations.switch', $organization))
            ->assertRedirect()
            ->assertSessionHas('current_organization_id', $organization->id);

        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('organizations.switch', $organization))
            ->assertForbidden();
    }

    public function test_the_create_page_is_available_to_authenticated_users(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('organizations.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('organizations/Create'));
    }

    public function test_an_organization_resolves_by_slug_rather_than_id(): void
    {
        $owner = User::factory()->create();
        $organization = $this->createOrganization($owner, 'Acme Corporation');

        $this->assertSame('acme-corporation', $organization->slug);

        $this->actingAs($owner)
            ->get('/organizations/acme-corporation')
            ->assertOk();

        $this->actingAs($owner)
            ->get('/organizations/'.$organization->id)
            ->assertNotFound();
    }
}
