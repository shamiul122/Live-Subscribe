<?php

namespace Database\Factories;

use App\Enums\ConversationPriority;
use App\Enums\ConversationStatus;
use App\Enums\SocialProvider;
use App\Models\ConnectedAccount;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'connected_account_id' => ConnectedAccount::factory(),
            'contact_id' => Contact::factory(),
            'provider' => SocialProvider::Facebook,
            'provider_conversation_id' => (string) Str::uuid(),
            'subject' => fake()->sentence(3),
            'status' => ConversationStatus::Open,
            'priority' => ConversationPriority::Normal,
            'assigned_to_id' => null,
            'last_message_at' => null,
            'last_message_preview' => null,
            'unread_count' => 0,
            'metadata' => ['source' => 'inbox'],
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => ['status' => ConversationStatus::Open]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => ConversationStatus::Pending]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['status' => ConversationStatus::Closed]);
    }

    public function spam(): static
    {
        return $this->state(fn (): array => ['status' => ConversationStatus::Spam]);
    }

    public function high(): static
    {
        return $this->state(fn (): array => ['priority' => ConversationPriority::High]);
    }

    public function urgent(): static
    {
        return $this->state(fn (): array => ['priority' => ConversationPriority::Urgent]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn (): array => ['assigned_to_id' => $user->id]);
    }
}
