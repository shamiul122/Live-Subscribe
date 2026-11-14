<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'avatar' => null,
            'notes' => null,
            'metadata' => ['source' => 'manual'],
        ];
    }

    public function named(string $name, ?string $email = null): static
    {
        return $this->state(fn (): array => [
            'name' => $name,
            'email' => $email ?? fake()->unique()->safeEmail(),
        ]);
    }
}
