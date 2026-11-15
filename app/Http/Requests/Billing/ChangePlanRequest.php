<?php

namespace App\Http\Requests\Billing;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChangePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization
            && $this->user()?->can('billing.manageBilling', $organization) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', 'exists:plans,slug'],
        ];
    }
}
