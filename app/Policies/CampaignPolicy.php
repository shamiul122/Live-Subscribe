<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationRole;

/**
 * Organization-scoped marketing campaign abilities.
 *
 * Viewing campaigns and their recipients is available to every active member.
 * Creating, editing, and lifecycle transitions require an owner or admin. The
 * policy never looks at a campaign in isolation: organization scoping is
 * enforced by the campaign service, so a campaign from another organization is
 * a 404 rather than a silent leak.
 */
class CampaignPolicy
{
    use ChecksOrganizationRole;

    public function view(User $user, Organization $organization): bool
    {
        return $this->isMember($user, $organization);
    }

    public function manage(User $user, Organization $organization): bool
    {
        return $this->isManager($user, $organization);
    }
}
