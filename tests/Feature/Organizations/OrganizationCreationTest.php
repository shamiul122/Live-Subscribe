<?php

namespace Tests\Feature\Organizations;

use App\Actions\Organizations\CreateOrganization;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizationCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_organizations(): void
    {
        $this->get(route('organizations.index'))
            ->assertRedirect(route('login'));
    }

    public function test_a_user_can_create_an_organization(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('organizations.store'), [
            'name' => 'Acme Inc',
        ]);

        $organization = Organization::firstOrFail();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('organizations.show', $organization));

        $this->assertSame($user->id, $organization->owner_id);
        $this->assertSame('Acme Inc', $organization->name);
        $this->assertSame('acme-inc', $organization->slug);

        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::Admin->value,
            'status' => OrganizationMembershipStatus::Active->value,
        ]);

        $membership = $organization->memberships()->firstOrFail();
        $this->assertNotNull($membership->joined_at);
    }

    public function test_organization_names_are_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('organizations.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_duplicate_names_receive_unique_slugs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('organizations.store'), ['name' => 'Acme']);
        $this->actingAs($user)->post(route('organizations.store'), ['name' => 'Acme']);

        $this->assertSame(
            ['acme', 'acme-2'],
            Organization::orderBy('id')->pluck('slug')->all(),
        );
    }

    public function test_the_index_only_lists_organizations_the_user_belongs_to(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = app(CreateOrganization::class)->handle($user, 'Mine');
        app(CreateOrganization::class)->handle($other, 'Theirs');

        $this->actingAs($user)
            ->get(route('organizations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('organizations/Index')
                ->has('organizations', 1)
                ->where('organizations.0.id', $mine->id)
                ->where('organizations.0.name', 'Mine')
                ->where('organizations.0.is_owner', true),
            );
    }
}
