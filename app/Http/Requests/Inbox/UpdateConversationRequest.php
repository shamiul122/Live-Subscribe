<?php

namespace App\Http\Requests\Inbox;

use App\Enums\ConversationPriority;
use App\Enums\ConversationStatus;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConversationRequest extends FormRequest
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
            'status' => ['sometimes', 'required', Rule::enum(ConversationStatus::class)],
            'priority' => ['sometimes', 'required', Rule::enum(ConversationPriority::class)],
        ];
    }
}
