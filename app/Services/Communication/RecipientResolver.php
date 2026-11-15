<?php

namespace App\Services\Communication;

use App\Enums\SocialProvider;
use App\Models\ConnectedAccount;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Organization;
use Illuminate\Validation\ValidationException;

/**
 * Resolves the provider-specific recipient for a conversation.
 *
 * The value is never guessed from free-form text. It comes from the stored
 * contact identity, the provider conversation id, or the phone/email stored on
 * the contact. Missing values produce a validation error instead of an
 * outbound call with an empty recipient.
 */
final class RecipientResolver
{
    public function resolve(Organization $organization, Conversation $conversation, ConnectedAccount $connectedAccount): string
    {
        if ($conversation->organization_id !== $organization->id) {
            throw ValidationException::withMessages(['message' => 'The conversation does not belong to this organization.']);
        }

        $contact = $conversation->contact;

        if (! $contact instanceof Contact) {
            throw ValidationException::withMessages([
                'message' => 'This conversation has no contact, so no provider recipient is available.',
            ]);
        }

        $metadata = $contact->metadata ?? [];

        $candidates = match ($connectedAccount->provider) {
            SocialProvider::Facebook => [
                $metadata['facebook_id'] ?? null,
                $metadata['psid'] ?? null,
                $conversation->provider_conversation_id,
            ],
            SocialProvider::Instagram => [
                $metadata['instagram_id'] ?? null,
                $metadata['igsid'] ?? null,
                $conversation->provider_conversation_id,
            ],
            SocialProvider::WhatsApp => [
                $metadata['whatsapp_number'] ?? null,
                $contact->phone,
                $conversation->provider_conversation_id,
            ],
            SocialProvider::Telegram => [
                $metadata['telegram_chat_id'] ?? null,
                $metadata['telegram_id'] ?? null,
                $conversation->provider_conversation_id,
            ],
            SocialProvider::Email => [
                $contact->email,
                $metadata['email'] ?? null,
            ],
        };

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                return trim((string) $candidate);
            }
        }

        throw ValidationException::withMessages([
            'message' => 'This contact has no usable '.$connectedAccount->provider->label().' recipient.',
        ]);
    }
}
