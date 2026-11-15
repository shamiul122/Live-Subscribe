<?php

namespace App\Http\Requests\Templates;

use App\Enums\MessageTemplateCategory;
use App\Enums\MessageTemplateStatus;
use App\Enums\SocialProvider;
use App\Models\Organization;
use App\Services\Templates\TemplateVariableService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization
            && $this->user()?->can('message_template.manage', $organization) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'channel' => ['nullable', Rule::enum(SocialProvider::class)],
            'category' => ['required', Rule::enum(MessageTemplateCategory::class)],
            'status' => ['required', Rule::enum(MessageTemplateStatus::class)],
            'subject' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:20000'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $content = $this->input('content');

            if (is_string($content) && $content !== '') {
                $unknown = app(TemplateVariableService::class)->unknown($content);

                if ($unknown !== []) {
                    $validator->errors()->add(
                        'content',
                        'Unknown variable(s): '.implode(', ', array_map(
                            static fn (string $key): string => '{{'.$key.'}}',
                            $unknown,
                        )).'.',
                    );
                }
            }

            if ($this->input('channel') === SocialProvider::Email->value
                && trim((string) $this->input('subject', '')) === '') {
                $validator->errors()->add(
                    'subject',
                    'A subject is required for email templates.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The template name is required.',
            'category.required' => 'Select a template category.',
            'category.enum' => 'The selected category is not supported.',
            'status.required' => 'Select a template status.',
            'status.enum' => 'The selected status is not supported.',
            'channel.enum' => 'The selected channel is not supported.',
            'content.required' => 'The template content is required.',
        ];
    }
}
