<?php

namespace App\Services\Inbox;

use App\Enums\ConversationPriority;
use App\Enums\ConversationStatus;
use App\Enums\SocialProvider;
use App\Models\ConnectedAccount;
use App\Models\Conversation;
use App\Models\Organization;
use App\Models\User;
use App\Services\Social\SocialAccountManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Organization-scoped conversation management for the unified inbox.
 */
final class ConversationService
{
    public function __construct(
        private readonly ContactService $contacts,
        private readonly ConversationEventService $events,
        private readonly MessageService $messages,
        private readonly SocialAccountManager $socialAccounts,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(Organization $organization, ?string $search = null, ?ConversationStatus $status = null, ?SocialProvider $provider = null): array
    {
        $conversations = Conversation::query()
            ->where('organization_id', $organization->id)
            ->with(['contact', 'connectedAccount'])
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->when($provider !== null, fn ($query) => $query->where('provider', $provider->value))
            ->orderBy('last_message_at', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        $results = [];

        foreach ($conversations as $conversation) {
            if ($this->matchesSearch($conversation, (string) $search)) {
                $results[] = $this->toArray($conversation);
            }
        }

        return $results;
    }

    public function find(Organization $organization, int $conversationId): Conversation
    {
        $conversation = Conversation::query()
            ->where('organization_id', $organization->id)
            ->with(['contact', 'connectedAccount', 'messages', 'events'])
            ->find($conversationId);

        if ($conversation === null) {
            throw new ModelNotFoundException("Conversation [{$conversationId}] was not found for this organization.");
        }

        return $conversation;
    }

    public function create(
        User $actor,
        Organization $organization,
        ConnectedAccount $connectedAccount,
        ?int $contactId,
        ?string $subject,
        ?string $providerConversationId = null,
    ): Conversation {
        if ($connectedAccount->organization_id !== $organization->id) {
            throw new ModelNotFoundException("Connected account [{$connectedAccount->id}] was not found for this organization.");
        }

        if ($providerConversationId !== null) {
            $existing = Conversation::query()
                ->where('organization_id', $organization->id)
                ->where('provider', $connectedAccount->provider->value)
                ->where('provider_conversation_id', $providerConversationId)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $conversation = Conversation::query()->create([
            'organization_id' => $organization->id,
            'connected_account_id' => $connectedAccount->id,
            'contact_id' => $contactId,
            'provider' => $connectedAccount->provider->value,
            'provider_conversation_id' => $providerConversationId,
            'subject' => $subject,
            'status' => ConversationStatus::Open,
            'priority' => ConversationPriority::Normal,
            'last_message_at' => now(),
            'last_message_preview' => $subject,
            'unread_count' => 0,
        ]);

        $this->events->record($actor, $organization, $conversation, ConversationEventService::CONVERSATION_CREATED, [
            'provider' => $connectedAccount->provider->value,
            'provider_conversation_id' => $providerConversationId,
        ]);

        return $conversation;
    }

    public function updateStatus(User $actor, Organization $organization, Conversation $conversation, ConversationStatus $status): Conversation
    {
        $conversation->status = $status;
        $conversation->save();

        $this->events->record($actor, $organization, $conversation, ConversationEventService::STATUS_CHANGED, [
            'status' => $status->value,
            'from_status' => $conversation->getOriginal('status')?->value,
        ]);

        return $conversation;
    }

    public function updatePriority(User $actor, Organization $organization, Conversation $conversation, ConversationPriority $priority): Conversation
    {
        $conversation->priority = $priority;
        $conversation->save();

        $this->events->record($actor, $organization, $conversation, ConversationEventService::PRIORITY_CHANGED, [
            'priority' => $priority->value,
            'from_priority' => $conversation->getOriginal('priority')?->value,
        ]);

        return $conversation;
    }

    public function assign(User $actor, Organization $organization, Conversation $conversation, ?int $assignedToId): Conversation
    {
        if ($assignedToId !== null) {
            $target = \App\Models\User::query()->find($assignedToId);

            if ($target === null || ! $organization->hasMember($target)) {
                throw ValidationException::withMessages([
                    'assigned_to_id' => 'The selected user is not an active member of this organization.',
                ]);
            }
        }

        $previous = $conversation->assigned_to_id;

        $conversation->assigned_to_id = $assignedToId;
        $conversation->save();

        if ($assignedToId !== ($previous ?? null)) {
            $this->events->record($actor, $organization, $conversation, ConversationEventService::CONVERSATION_ASSIGNED, [
                'assigned_to_id' => $assignedToId,
            ]);
        }

        return $conversation;
    }

    public function markAsRead(User $actor, Organization $organization, Conversation $conversation): Conversation
    {
        if ($conversation->organization_id !== $organization->id) {
            throw new ModelNotFoundException("Conversation [{$conversation->id}] was not found for this organization.");
        }

        $this->messages->markRead($actor, $organization, $conversation);

        return $conversation;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Conversation $conversation): array
    {
        $conversation->loadMissing(['contact', 'connectedAccount']);

        $connectedAccount = $conversation->connectedAccount;
        $socialSummary = $connectedAccount instanceof ConnectedAccount ? $this->socialAccounts->summary($connectedAccount) : null;

        $contact = $conversation->contact;
        $contactSummary = $contact instanceof \App\Models\Contact ? $this->contacts->toArray($contact) : null;

        return [
            'id' => $conversation->id,
            'organization_id' => $conversation->organization_id,
            'connected_account_id' => $conversation->connected_account_id,
            'contact_id' => $conversation->contact_id,
            'provider' => $conversation->provider->value,
            'provider_conversation_id' => $conversation->provider_conversation_id,
            'subject' => $conversation->subject,
            'status' => $conversation->status->value,
            'status_label' => $conversation->status->label(),
            'priority' => $conversation->priority->value,
            'priority_label' => $conversation->priority->label(),
            'assigned_to_id' => $conversation->assigned_to_id,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'last_message_preview' => $conversation->last_message_preview,
            'unread_count' => $conversation->unread_count,
            'metadata' => $conversation->metadata,
            'created_at' => $conversation->created_at?->toIso8601String(),
            'updated_at' => $conversation->updated_at?->toIso8601String(),
            'contact' => $contactSummary,
            'connected_account' => $socialSummary,
        ];
    }

    private function matchesSearch(Conversation $conversation, string $search): bool
    {
        if ($search === '') {
            return true;
        }

        $contact = $conversation->contact;

        return str_contains(strtolower((string) $conversation->subject), strtolower($search))
            || str_contains(strtolower((string) $conversation->last_message_preview), strtolower($search))
            || ($contact instanceof \App\Models\Contact
                && (str_contains(strtolower($contact->name), strtolower($search))
                    || str_contains(strtolower((string) $contact->email), strtolower($search))));
    }
}
