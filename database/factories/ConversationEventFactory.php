<?php

namespace Database\Factories;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationEvent>
 */
class ConversationEventFactory extends Factory
{
    protected $model = ConversationEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'conversation_id' => Conversation::factory(),
            'event_type' => 'conversation_created',
            'actor_id' => null,
            'event_data' => ['source' => 'inbox'],
            'created_at' => now(),
        ];
    }

    public function conversationCreated(): static
    {
        return $this->state(fn (): array => ['event_type' => 'conversation_created', 'event_data' => ['source' => 'inbox']]);
    }

    public function messageReceived(): static
    {
        return $this->state(fn (): array => ['event_type' => 'message_received']);
    }

    public function messageSent(): static
    {
        return $this->state(fn (): array => ['event_type' => 'message_sent']);
    }

    public function conversationRead(): static
    {
        return $this->state(fn (): array => ['event_type' => 'conversation_read']);
    }

    public function conversationClosed(): static
    {
        return $this->state(fn (): array => ['event_type' => 'conversation_closed']);
    }
}
