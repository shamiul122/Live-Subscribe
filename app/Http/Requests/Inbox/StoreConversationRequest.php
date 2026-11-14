<?php

namespace App\Http\Requests\Inbox;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization
            && $this->user()?->can('inbox.manage', $organization) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'connected_account_id' => ['required', 'integer'],
            'contact_id' => ['nullable', 'integer'],
            'subject' => ['nullable', 'string', 'max:255'],
            'provider_conversation_id' => ['nullable', 'string', 'max:160'],
        ];
    }
}
