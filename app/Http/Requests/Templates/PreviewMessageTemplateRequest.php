<?php

namespace App\Http\Requests\Templates;

use App\Models\Organization;
use App\Services\Templates\TemplateVariableService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates an ad-hoc preview request. The content is never persisted and is
 * only ever rendered with plain string substitution.
 */
class PreviewMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization
            && $this->user()?->can('message_template.view', $organization) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:20000'],
            'values' => ['nullable', 'array'],
            'values.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $content = $this->input('content');

            if (! is_string($content) || $content === '') {
                return;
            }

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
        });
    }
}
