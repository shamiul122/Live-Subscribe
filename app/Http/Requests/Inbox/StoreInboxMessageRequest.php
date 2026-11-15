<?php

namespace App\Http\Requests\Inbox;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreInboxMessageRequest extends FormRequest
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
        $maxKb = (int) config('communication.attachments.max_size_kb', 10240);
        $maxFiles = (int) config('communication.attachments.max_per_message', 5);
        $mimes = (array) config('communication.attachments.allowed_mimes', []);

        $attachmentRules = ['file', 'max:'.$maxKb];

        if ($mimes !== []) {
            $attachmentRules[] = 'mimetypes:'.implode(',', array_map('strval', $mimes));
        }

        return [
            'conversation_id' => ['required', 'integer'],
            'connected_account_id' => ['required', 'integer'],
            'body' => ['nullable', 'string', 'max:4000', 'required_without:attachments'],
            'attachments' => ['nullable', 'array', 'max:'.$maxFiles],
            'attachments.*' => $attachmentRules,
            'idempotency_key' => ['nullable', 'string', 'max:80'],
        ];
    }
}
