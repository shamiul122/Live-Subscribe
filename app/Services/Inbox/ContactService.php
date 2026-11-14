<?php

namespace App\Services\Inbox;

use App\Models\Contact;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Organization-scoped contact management for the unified inbox.
 */
final class ContactService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(Organization $organization, ?string $search = null): array
    {
        $query = Contact::query()->where('organization_id', $organization->id);

        if ($search !== null && $search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        $contacts = $query->orderBy('id')->get();

        return $contacts
            ->map(fn (Contact $contact): array => $this->toArray($contact))
            ->values()
            ->all();
    }

    public function find(Organization $organization, int $contactId): Contact
    {
        $contact = Contact::query()
            ->where('organization_id', $organization->id)
            ->find($contactId);

        if ($contact === null) {
            throw new ModelNotFoundException("Contact [{$contactId}] was not found for this organization.");
        }

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, Organization $organization, array $data): Contact
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:254', 'email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'avatar' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ])->validate();

        $email = $validated['email'] ?? null;

        if (is_string($email) && $email !== '') {
            $duplicate = Contact::query()
                ->where('organization_id', $organization->id)
                ->where('email', $email)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'email' => 'A contact with this email already exists for this organization.',
                ]);
            }
        }

        $contact = Contact::query()->create([
            'organization_id' => $organization->id,
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'avatar' => $validated['avatar'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Organization $organization, Contact $contact, array $data): Contact
    {
        if ($contact->organization_id !== $organization->id) {
            throw new ModelNotFoundException("Contact [{$contact->id}] was not found for this organization.");
        }

        $validated = Validator::make($data, [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:254', 'email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'avatar' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ])->validate();

        $email = $validated['email'] ?? null;

        if (is_string($email) && $email !== '') {
            $duplicate = Contact::query()
                ->where('organization_id', $organization->id)
                ->where('email', $email)
                ->where('id', '!=', $contact->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'email' => 'A contact with this email already exists for this organization.',
                ]);
            }
        }

        $contact->fill($validated);
        $contact->save();

        return $contact;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'organization_id' => $contact->organization_id,
            'name' => $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'avatar' => $contact->avatar,
            'notes' => $contact->notes,
            'metadata' => $contact->metadata,
            'created_at' => $contact->created_at?->toIso8601String(),
            'updated_at' => $contact->updated_at?->toIso8601String(),
        ];
    }
}
