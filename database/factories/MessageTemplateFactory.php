<?php

namespace Database\Factories;

use App\Enums\MessageTemplateCategory;
use App\Enums\MessageTemplateStatus;
use App\Enums\SocialProvider;
use App\Models\MessageTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageTemplate>
 */
class MessageTemplateFactory extends Factory
{
    protected $model = MessageTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by_id' => User::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'channel' => SocialProvider::Email->value,
            'category' => MessageTemplateCategory::Promotional,
            'status' => MessageTemplateStatus::Draft,
            'subject' => fake()->sentence(4),
            'content' => 'Hello {{customer_name}}, welcome to {{company_name}}.',
            'variables' => ['customer_name', 'company_name'],
            'metadata' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => MessageTemplateStatus::Active,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => MessageTemplateStatus::Archived,
        ]);
    }
}
