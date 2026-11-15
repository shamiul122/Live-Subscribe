export type MessageTemplateStatusValue = 'draft' | 'active' | 'archived';

export type MessageTemplateCategoryValue =
    | 'promotional'
    | 'welcome'
    | 'follow_up'
    | 'customer_support'
    | 'order_update'
    | 'product_announcement'
    | 'custom';

export type MessageTemplateOption = {
    value: string;
    label: string;
};

export type TemplateOrganization = {
    id: number;
    name: string;
    slug: string;
};

export type TemplateVariableDefinition = {
    key: string;
    label: string;
    sample: string;
    description: string;
};

export type MessageTemplatePermissions = {
    manage: boolean;
};

export type MessageTemplateRecord = {
    id: number;
    organization_id: number;
    name: string;
    description: string | null;
    channel: string | null;
    category: MessageTemplateCategoryValue;
    category_label: string;
    status: MessageTemplateStatusValue;
    status_label: string;
    subject: string | null;
    content: string;
    variables: string[];
    variable_details: TemplateVariableDefinition[];
    unknown_variables: string[];
    metadata: Record<string, unknown> | null;
    created_by_id: number | null;
    created_by: { id: number; name: string } | null;
    created_at: string | null;
    updated_at: string | null;
    is_editable: boolean;
    is_reusable: boolean;
};

export type MessageTemplateFilters = {
    search: string;
    channel: string | null;
    category: string | null;
    status: string | null;
};

export type MessageTemplateFormData = {
    name: string;
    description: string;
    channel: string;
    category: string;
    status: string;
    subject: string;
    content: string;
};

export type ReusableMessageTemplate = {
    id: number;
    name: string;
    channel: string | null;
    category: string;
    category_label: string;
    subject: string | null;
    content: string;
    variables: string[];
};

export type MessageTemplatePreviewResult = {
    subject: string | null;
    content: string;
    used_variables: string[];
    unknown_variables: string[];
    values: Record<string, string>;
};
