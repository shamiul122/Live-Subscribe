<?php

namespace Database\Factories;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationMembership>
 */
class OrganizationMembershipFactory extends Factory
{
    protected $model = OrganizationMembership::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'role' => OrganizationRole::Member,
            'status' => OrganizationMembershipStatus::Active,
            'joined_at' => now(),
        ];
    }

    /**
     * role is guarded on the model, so it is set explicitly.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): OrganizationMembership
    {
        $role = $attributes['role'] ?? OrganizationRole::Member;
        unset($attributes['role']);

        $membership = parent::newModel($attributes);

        $membership->forceFill([
            'role' => $role instanceof OrganizationRole
                ? $role
                : OrganizationRole::from($role),
        ]);

        return $membership;
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => OrganizationRole::Admin]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => OrganizationMembershipStatus::Inactive]);
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn () => ['organization_id' => $organization->id]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }
}
