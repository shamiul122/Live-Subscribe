<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationRole;

/**
 * Organization-scoped unified inbox abilities.
 *
 * Viewing conversations and contacts is available to every active member.
 * Triage mutations (create conversation, change status, change priority, assign,
 * manage contacts) require an owner or admin.
 */
class InboxPolicy
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
