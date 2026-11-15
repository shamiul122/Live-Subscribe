<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationRole;

/**
 * Organization-scoped message template abilities.
 *
 * Viewing templates is available to every active member. Creating, editing,
 * archiving and deleting require an owner or admin. The policy never looks at a
 * template in isolation: organization scoping is enforced by
 * MessageTemplateService, so a template from another organization is a 404
 * rather than a silent leak.
 */
class MessageTemplatePolicy
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
