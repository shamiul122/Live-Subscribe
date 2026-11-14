<?php

namespace Database\Factories;

use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'conversation_id' => Conversation::factory(),
            'provider_message_id' => (string) Str::uuid(),
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Text,
            'body' => fake()->sentence(6),
            'sender_type' => 'contact',
            'sender_id' => null,
            'sent_at' => now(),
            'delivered_at' => null,
            'read_at' => null,
            'failed_at' => null,
            'failure_reason' => null,
            'metadata' => ['source' => 'test'],
        ];
    }

    public function outbound(): static
    {
        return $this->state(fn (): array => [
            'direction' => MessageDirection::Outbound,
            'sender_type' => 'user',
            'sender_id' => null,
        ]);
    }

    public function providerMessageId(string $id): static
    {
        return $this->state(fn (): array => ['provider_message_id' => $id]);
    }
}
