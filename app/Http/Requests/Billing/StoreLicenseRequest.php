<?php

namespace App\Http\Requests\Billing;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization
            && $this->user()?->can('billing.manageLicenses', $organization) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:80'],
        ];
    }
}
