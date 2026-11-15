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

export type PlanSummary = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    price: { minor: number; currency: string; formatted: string };
    interval: { value: string; label: string; count: number; label_full: string };
    interval_label: string;
    has_trial: boolean;
    trial_days: number;
    is_active: boolean;
    is_archived: boolean;
    is_purchasable: boolean;
    features: PlanFeatureSummary[];
};

export type PlanFeatureSummary = {
    feature: string;
    label: string;
    type: 'boolean' | 'metered' | 'resource';
    limit: number | null;
    included: boolean;
    unlimited: boolean;
    unit: string;
    limit_label?: string;
};

export type PlanDetails = PlanSummary & {
    interval_count: number;
};

export type LicenseSummary = {
    id: number;
    label?: string | null;
    status: string;
    status_label: string;
    is_active: boolean;
    is_usable: boolean;
    masked_key: string;
    activated_at?: string | null;
    expires_at?: string | null;
    suspended_at?: string | null;
    revoked_at?: string | null;
    created_at: string;
};

export type LicenseFormErrors = {
    label?: string;
};

export type ConnectedAccountStatus = 'pending' | 'active' | 'revoked';

export type BillingOrganizationPermissions = {
    manage: boolean;
    manage_licenses: boolean;
    view_history: boolean;
};

export type ConnectedAccountSummary = {
    id: number;
    organization_id: number;
    provider: string;
    provider_label: string;
    provider_account_id: string;
    account_name: string;
    account_username: string | null;
    account_type: string | null;
    status: ConnectedAccountStatus;
    status_label: string;
    token_expires_at: string | null;
    last_synced_at: string | null;
    metadata: Record<string, unknown> | null;
    is_active: boolean;
    is_usable: boolean;
    created_at: string;
    updated_at: string;
};

export type ConversationStatusValue = 'open' | 'pending' | 'closed' | 'spam';

export type ConversationPriorityValue = 'normal' | 'high' | 'urgent';

export type InboxProvider = 'facebook' | 'instagram' | 'whatsapp' | 'telegram' | 'email';

export type InboxPermissions = {
    manage: boolean;
};

export type InboxContactSummary = {
    id: number;
    organization_id: number;
    name: string;
    email: string | null;
    phone: string | null;
    avatar: string | null;
    notes: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string | null;
    updated_at: string | null;
};

export type InboxConnectedAccountSummary = {
    id: number;
    organization_id: number;
    provider: string;
    provider_label: string;
    provider_account_id: string;
    account_name: string;
    account_username: string | null;
    account_type: string | null;
    status: ConnectedAccountStatus;
    status_label: string;
    token_expires_at: string | null;
    last_synced_at: string | null;
    metadata: Record<string, unknown> | null;
    is_active: boolean;
    is_usable: boolean;
    created_at: string;
    updated_at: string;
};

export type InboxConversationSummary = {
    id: number;
    organization_id: number;
    connected_account_id: number;
    contact_id: number | null;
    provider: string;
    provider_conversation_id: string | null;
    subject: string | null;
    status: ConversationStatusValue;
    status_label: string;
    priority: ConversationPriorityValue;
    priority_label: string;
    assigned_to_id: number | null;
    last_message_at: string | null;
    last_message_preview: string | null;
    unread_count: number;
    metadata: Record<string, unknown> | null;
    created_at: string | null;
    updated_at: string | null;
    contact: InboxContactSummary | null;
    connected_account: InboxConnectedAccountSummary | null;
};

export type InboxDeliveryStatus = 'queued' | 'pending' | 'processing' | 'sending' | 'accepted' | 'sent' | 'delivered' | 'read' | 'failed';

export type InboxAttachmentSummary = {
    id: number;
    original_name: string;
    mime_type: string;
    size: number;
    is_image: boolean;
    attachment_type: string;
};

export type InboxMessage = {
    id: number;
    organization_id: number;
    conversation_id: number;
    provider_message_id: string | null;
    direction: 'inbound' | 'outbound';
    direction_label: string;
    message_type: string;
    message_type_label: string;
    delivery_status: InboxDeliveryStatus | null;
    delivery_status_label: string | null;
    body: string | null;
    sender_type: string | null;
    sender_id: number | null;
    sent_at: string | null;
    delivered_at: string | null;
    read_at: string | null;
    failed_at: string | null;
    failure_reason: string | null;
    metadata: Record<string, unknown> | null;
    is_retryable: boolean;
    last_error_code: string | null;
    attachments: InboxAttachmentSummary[];
    created_at: string | null;
    updated_at: string | null;
};

export type InboxCommunicationCapability = 'outbound_text' | 'attachments' | 'delivery_status' | 'inbound_webhooks';

export type InboxCommunicationStatus = {
    provider: string | null;
    configured: boolean;
    usable: boolean;
    error_code: string | null;
    error_message: string | null;
    capabilities: Record<InboxCommunicationCapability, boolean>;
    capability_reasons: Record<InboxCommunicationCapability, string | null>;
    attachments: {
        max_size_kb: number;
        max_per_message: number;
    };
};