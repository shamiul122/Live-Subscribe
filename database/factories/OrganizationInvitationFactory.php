<?php

namespace Database\Factories;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrganizationInvitation>
 */
class OrganizationInvitationFactory extends Factory
{
    protected $model = OrganizationInvitation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'invited_by_id' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => OrganizationRole::Member,
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDays(3),
        ];
    }

    /**
     * role and token_hash are guarded on the model, so they are set explicitly.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): OrganizationInvitation
    {
        $role = $attributes['role'] ?? OrganizationRole::Member;
        $tokenHash = $attributes['token_hash'] ?? hash('sha256', Str::random(64));

        unset($attributes['role'], $attributes['token_hash']);

        $invitation = parent::newModel($attributes);

        $invitation->forceFill([
            'role' => $role instanceof OrganizationRole
                ? $role
                : OrganizationRole::from($role),
            'token_hash' => $tokenHash,
        ]);

        return $invitation;
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => OrganizationRole::Admin]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function accepted(?User $user = null): static
    {
        return $this->state(fn () => [
            'accepted_at' => now(),
            'accepted_by_id' => $user?->id,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn () => ['organization_id' => $organization->id]);
    }
}
