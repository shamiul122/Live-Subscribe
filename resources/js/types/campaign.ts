export type CampaignStatusValue =
    | 'draft'
    | 'ready'
    | 'scheduled'
    | 'running'
    | 'paused'
    | 'completed'
    | 'cancelled'
    | 'failed';

export type CampaignTypeValue =
    | 'promotional'
    | 'product_announcement'
    | 'customer_engagement'
    | 'follow_up'
    | 'custom';

export type CampaignAudienceTypeValue = 'all' | 'selected';

export type CampaignRecipientStatusValue =
    | 'pending'
    | 'queued'
    | 'sending'
    | 'sent'
    | 'delivered'
    | 'read'
    | 'failed'
    | 'skipped';

export type CampaignOption = {
    value: string;
    label: string;
};

export type CampaignOrganization = {
    id: number;
    name: string;
    slug: string;
};

export type CampaignPermissions = {
    manage: boolean;
};

export type CampaignConnectedAccountOption = {
    id: number;
    provider: string;
    provider_label: string;
    account_name: string;
    account_username: string | null;
    status: string;
    status_label: string;
    is_active: boolean;
};

export type CampaignConnectedAccountSummary = {
    id: number;
    provider: string;
    provider_label: string;
    account_name: string;
    account_username: string | null;
    status: string;
    status_label: string;
};

export type CampaignContactSummary = {
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

export type CampaignAudienceSnapshotSummary = {
    id: number;
    audience_type: CampaignAudienceTypeValue;
    contact_count: number;
    captured_by_id: number | null;
    captured_at: string | null;
};

export type CampaignCapabilities = {
    edit: boolean;
    mark_ready: boolean;
    pause: boolean;
    resume: boolean;
    cancel: boolean;
    prepare_recipients: boolean;
};

export type CampaignStatistics = {
    total: number;
    pending: number;
    queued: number;
    sending: number;
    sent: number;
    delivered: number;
    failed: number;
    skipped: number;
    by_status: Record<CampaignRecipientStatusValue, number>;
    delivery_rate: number;
    has_recipients: boolean;
};

export type CampaignRecord = {
    id: number;
    organization_id: number;
    name: string;
    description: string | null;
    type: CampaignTypeValue;
    type_label: string;
    channel: string | null;
    status: CampaignStatusValue;
    status_label: string;
    message_content: string | null;
    connected_account_id: number | null;
    connected_account: CampaignConnectedAccountSummary | null;
    audience_type: CampaignAudienceTypeValue;
    audience_type_label: string;
    audience_config: Record<string, unknown> | null;
    audience_size: number;
    scheduled_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
    metadata: Record<string, unknown> | null;
    created_by_id: number | null;
    created_by: { id: number; name: string } | null;
    created_at: string | null;
    updated_at: string | null;
    is_editable: boolean;
    is_terminal: boolean;
    recipients_count: number | null;
    audience_snapshot: CampaignAudienceSnapshotSummary | null;
    can: CampaignCapabilities;
    statistics?: CampaignStatistics;
};

export type CampaignRecipientSummary = {
    id: number;
    organization_id: number;
    campaign_id: number;
    contact_id: number | null;
    status: CampaignRecipientStatusValue;
    status_label: string;
    delivery_status: string | null;
    delivery_status_label: string | null;
    error_message: string | null;
    queued_at: string | null;
    sent_at: string | null;
    delivered_at: string | null;
    read_at: string | null;
    failed_at: string | null;
    provider_message_id: string | null;
    metadata: Record<string, unknown> | null;
    contact: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
    } | null;
    created_at: string | null;
    updated_at: string | null;
};

export type CampaignFilters = {
    search: string;
    status: string | null;
    type: string | null;
    channel: string | null;
};

export type CampaignRecipientFilters = {
    status: string | null;
};

export type CampaignFormData = {
    name: string;
    description: string;
    type: string;
    channel: string;
    connected_account_id: number | null;
    message_content: string;
    audience_type: string;
    audience_config: { contact_ids: number[] };
    scheduled_at: string;
};
