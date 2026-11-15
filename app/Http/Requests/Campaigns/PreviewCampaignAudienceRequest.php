<?php

namespace App\Http\Requests\Campaigns;

use App\Enums\CampaignAudienceType;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PreviewCampaignAudienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization
            && $this->user()?->can('campaign.manage', $organization) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'audience_type' => ['required', Rule::enum(CampaignAudienceType::class)],
            'audience_config' => ['nullable', 'array'],
            'audience_config.contact_ids' => ['nullable', 'array'],
            'audience_config.contact_ids.*' => ['integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('audience_type') !== CampaignAudienceType::Selected->value) {
                return;
            }

            $ids = $this->input('audience_config.contact_ids');

            if (! is_array($ids) || $ids === []) {
                $validator->errors()->add(
                    'audience_config.contact_ids',
                    'Select at least one contact for a selected audience.',
                );
            }
        });
    }
}
