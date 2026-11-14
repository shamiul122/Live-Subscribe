export type OrganizationRole = 'admin' | 'member';

export type OrganizationMembershipStatus = 'active' | 'inactive';

export type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
    role: OrganizationRole;
    is_owner: boolean;
    members_count?: number;
};

export type OrganizationDetail = {
    id: number;
    name: string;
    slug: string;
    owner: {
        id: number;
        name: string;
        email: string;
    };
    created_at: string | null;
};

export type OrganizationMember = {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
    };
    role: OrganizationRole;
    role_label: string;
    status: OrganizationMembershipStatus;
    joined_at: string | null;
    is_owner: boolean;
};

export type OrganizationInvitation = {
    id: number;
    email: string;
    role: OrganizationRole;
    role_label: string;
    invited_by: string | null;
    expires_at: string;
    is_expired: boolean;
};

export type OrganizationPermissions = {
    update: boolean;
    delete: boolean;
    invite: boolean;
    revoke: boolean;
    update_role: boolean;
    remove_member: boolean;
    transfer_ownership: boolean;
    leave: boolean;
};
