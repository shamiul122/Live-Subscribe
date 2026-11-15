<?php

namespace App\Http\Requests\Inbox;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreInboxAttachmentRequest extends FormRequest
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
        $mimes = (array) config('communication.attachments.allowed_mimes', []);

        $rules = ['required', 'file', 'max:'.$maxKb];

        if ($mimes !== []) {
            $rules[] = 'mimetypes:'.implode(',', array_map('strval', $mimes));
        }

        return [
            'file' => $rules,
            'message_id' => ['nullable', 'integer'],
        ];
    }
}
