<?php

namespace App\Http\Requests\Campaigns;

use App\Enums\CampaignAudienceType;
use App\Enums\CampaignType;
use App\Enums\SocialProvider;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCampaignRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(CampaignType::class)],
            'channel' => ['nullable', Rule::enum(SocialProvider::class)],
            'connected_account_id' => ['nullable', 'integer', 'min:1'],
            'message_content' => ['nullable', 'string', 'max:10000'],
            'audience_type' => ['required', Rule::enum(CampaignAudienceType::class)],
            'audience_config' => ['nullable', 'array'],
            'audience_config.contact_ids' => ['nullable', 'array'],
            'audience_config.contact_ids.*' => ['integer', 'min:1'],
            'scheduled_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The campaign name is required.',
            'type.required' => 'Select a campaign type.',
            'type.enum' => 'The selected campaign type is not supported.',
            'channel.enum' => 'The selected channel is not supported.',
            'audience_type.required' => 'Select an audience type.',
            'audience_type.enum' => 'The selected audience type is not supported.',
            'audience_config.contact_ids.required' => 'Select at least one contact for a selected audience.',
        ];
    }
}
