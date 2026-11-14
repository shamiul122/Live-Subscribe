<?php

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\User;

class ProvisionPersonalOrganization
{
    public function __construct(private readonly CreateOrganization $createOrganization) {}

    /**
     * Provision the default organization that every registered user owns.
     */
    public function handle(User $user): Organization
    {
        return $this->createOrganization->handle(
            $user,
            "{$user->name}'s Organization",
        );
    }
}
