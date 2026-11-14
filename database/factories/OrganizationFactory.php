<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'owner_id' => User::factory(),
        ];
    }

    /**
     * owner_id is guarded on the model, so it is associated explicitly.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): Organization
    {
        $owner = $attributes['owner_id'] ?? null;
        unset($attributes['owner_id']);

        $organization = parent::newModel($attributes);

        if ($owner !== null) {
            $organization->owner()->associate($owner);
        }

        return $organization;
    }

    /**
     * Attach an existing owner to the organization.
     */
    public function ownedBy(User $owner): static
    {
        return $this->state(fn () => ['owner_id' => $owner]);
    }
}
