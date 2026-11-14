<?php

namespace App\Actions\Organizations;

use App\Actions\Organizations\Concerns\EnsuresOrganizationPrivileges;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateOrganization
{
    use EnsuresOrganizationPrivileges;

    public function handle(Organization $organization, User $actor, string $name): Organization
    {
        $this->ensureManager($organization, $actor);

        $name = trim($name);

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'The organization name is required.',
            ]);
        }

        return DB::transaction(function () use ($organization, $name): Organization {
            $organization->update(['name' => $name]);

            return $organization->refresh();
        });
    }
}
