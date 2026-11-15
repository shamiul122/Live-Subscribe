<?php

namespace App\Http\Requests\Billing;

use App\Models\License;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;

class RotateLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');
        $license = $this->route('license');

        return $organization instanceof Organization
            && $license instanceof License
            && $license->organization_id === $organization->id
            && $this->user()?->can('billing.manageLicenses', $organization) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
