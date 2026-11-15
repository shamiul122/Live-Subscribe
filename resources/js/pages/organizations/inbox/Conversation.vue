<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    ConversationPriorityValue,
    ConversationStatusValue,
    InboxCommunicationStatus,
    InboxConversationSummary,
    InboxDeliveryStatus,
    InboxMessage,
    InboxPermissions,
    InboxProvider,
} from '@/types';

const props = defineProps<{
    conversation: InboxConversationSummary;
    messages: InboxMessage[];
    statuses: ConversationStatusValue[];
    priorities: ConversationPriorityValue[];
    providers: InboxProvider[];
    permissions: InboxPermissions;
    communication: InboxCommunicationStatus;
    members: Array<{
        id: number;
        name: string;
        email: string;
        is_owner: boolean;
    }>;
    organization: { id: number; name: string; slug: string };
}>();

const selectedStatus = ref<string>(props.conversation.status);
const selectedPriority = ref<string>(props.conversation.priority);
const selectedAssignee = ref<string>(props.conversation.assigned_to_id !== null ? String(props.conversation.assigned_to_id) : '');

const composerBody = ref('');
const composerFiles = ref<File[]>([]);
const composerError = ref<string | null>(null);
const attachmentInput = ref<HTMLInputElement | null>(null);
const sending = ref(false);

const canSendText = computed(() => props.communication.usable && props.communication.capabilities.outbound_text === true);
const canAttach = computed(() => canSendText.value && props.communication.capabilities.attachments === true);
const attachmentsReason = computed(() => props.communication.capability_reasons.attachments ?? null);
const canSubmit = computed(() => sending.value === false && (composerBody.value.trim() !== '' || composerFiles.value.length > 0));

function onFilesSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    composerFiles.value = Array.from(input.files ?? []).slice(0, props.communication.attachments.max_per_message);
    composerError.value = null;
}

function sendMessage(): void {
    if (!canSubmit.value || !canSendText.value) {
        return;
    }

    composerError.value = null;
    sending.value = true;

    const idempotencyKey = typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(16).slice(2)}`;

    router.post(
        `/organizations/${props.organization.slug}/inbox/messages`,
        {
            conversation_id: props.conversation.id,
            connected_account_id: props.conversation.connected_account_id,
            body: composerBody.value,
            attachments: composerFiles.value,
            idempotency_key: idempotencyKey,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                composerBody.value = '';
                composerFiles.value = [];

                if (attachmentInput.value !== null) {
                    attachmentInput.value.value = '';
                }
            },
            onError: (errors) => {
                composerError.value = Object.values(errors)[0] ?? 'The message could not be queued.';
            },
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}

function retryMessage(message: InboxMessage): void {
    router.post(
        `/organizations/${props.organization.slug}/inbox/messages/${message.id}/retry`,
        {},
        { preserveScroll: true },
    );
}

function statusClass(status: ConversationStatusValue): string {
    switch (status) {
        case 'open':
            return 'bg-green-100 text-green-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'closed':
            return 'bg-gray-100 text-gray-800';
        case 'spam':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function priorityClass(priority: ConversationPriorityValue): string {
    switch (priority) {
        case 'urgent':
            return 'bg-red-100 text-red-800';
        case 'high':
            return 'bg-orange-100 text-orange-800';
        case 'normal':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function deliveryClass(status: InboxDeliveryStatus): string {
    switch (status) {
        case 'delivered':
        case 'read':
            return 'bg-green-100 text-green-800';
        case 'failed':
            return 'bg-red-100 text-red-800';
        case 'queued':
        case 'pending':
        case 'processing':
        case 'sending':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function conversationHref(): string {
    return `/organizations/${props.organization.slug}/inbox/conversations/${props.conversation.id}`;
}

function markAsRead(): void {
    router.post(`${conversationHref()}/mark-read`, {}, { preserveScroll: true });
}

function updateStatus(): void {
    if (props.permissions.manage !== true) {
        return;
    }

    router.put(conversationHref(), { status: selectedStatus.value }, { preserveScroll: true });
}

function updatePriority(): void {
    if (props.permissions.manage !== true) {
        return;
    }

    router.put(conversationHref(), { priority: selectedPriority.value }, { preserveScroll: true });
}

function assign(): void {
    if (props.permissions.manage !== true) {
        return;
    }

    router.post(`${conversationHref()}/assign`, { assigned_to_id: selectedAssignee.value === '' ? null : Number(selectedAssignee.value) }, { preserveScroll: true });
}

function formatDate(value: string | null): string {
    if (value === null) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString();
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function downloadUrl(attachmentId: number): string {
    return `/organizations/${props.organization.slug}/inbox/attachments/${attachmentId}/download`;
}

type InboundAttachmentDescriptor = {
    type?: string;
    file_name?: string | null;
    mime_type?: string | null;
};

function inboundAttachments(message: InboxMessage): InboundAttachmentDescriptor[] {
    const raw = message.metadata?.inbound_attachments;

    if (!Array.isArray(raw)) {
        return [];
    }

    return raw.filter((value): value is InboundAttachmentDescriptor => typeof value === 'object' && value !== null);
}

</script>

<template>
    <Head title="Conversation" />

    <div class="p-6">
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-semibold">
                    {{ conversation.contact?.name ?? conversation.subject ?? 'Conversation' }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ conversation.connected_account?.provider_label ?? conversation.provider }}
                    <span v-if="conversation.connected_account?.account_name"> • {{ conversation.connected_account.account_name }}</span>
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium"
                    :class="statusClass(conversation.status)"
                >
                    {{ conversation.status_label }}
                </span>

                <span
                    class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium"
                    :class="priorityClass(conversation.priority)"
                >
                    {{ conversation.priority_label }} priority
                </span>

                <Button size="sm" @click="markAsRead">Mark as read</Button>
            </div>
        </div>

        <div v-if="props.permissions.manage" class="rounded-lg border p-4 mb-6">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="conversation-status">Status</Label>
                    <select
                        id="conversation-status"
                        v-model="selectedStatus"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                        <option v-for="status in props.statuses" :key="status" :value="status">{{ status }}</option>
                    </select>
                </div>

                <div class="grid gap-2">
                    <Label for="conversation-priority">Priority</Label>
                    <select
                        id="conversation-priority"
                        v-model="selectedPriority"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                        <option v-for="priority in props.priorities" :key="priority" :value="priority">{{ priority }}</option>
                    </select>
                </div>

                <div class="grid gap-2">
                    <Label for="conversation-assignee">Assignee</Label>
                    <select
                        id="conversation-assignee"
                        v-model="selectedAssignee"
                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                        <option :value="''">Unassigned</option>
                        <option v-for="member in props.members" :key="member.id" :value="String(member.id)">{{ member.name }}</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 flex gap-2">
                <Button size="sm" @click="updateStatus">Save status</Button>
                <Button size="sm" variant="outline" @click="updatePriority">Save priority</Button>
                <Button size="sm" variant="outline" @click="assign">Assign</Button>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            <div class="md:col-span-2">
                <h2 class="text-lg font-semibold mb-4">Messages</h2>

                <div v-if="props.messages.length === 0" class="rounded-lg border p-6 text-sm text-muted-foreground">
                    No messages in this conversation yet.
                </div>

                <div v-else class="grid gap-3">
                    <div
                        v-for="message in props.messages"
                        :key="message.id"
                        class="rounded-lg border p-3"
                        :class="message.direction === 'inbound' ? 'bg-muted' : 'bg-primary/5'"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-medium uppercase text-muted-foreground">
                                    {{ message.direction_label }}
                                </span>
                                <span
                                    v-if="message.direction === 'outbound' && message.delivery_status"
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium"
                                    :class="deliveryClass(message.delivery_status)"
                                >
                                    {{ message.delivery_status_label ?? message.delivery_status }}
                                </span>
                            </div>
                            <span class="text-xs text-muted-foreground">{{ formatDate(message.sent_at) }}</span>
                        </div>

                        <p v-if="message.body" class="mt-2 text-sm whitespace-pre-wrap">{{ message.body }}</p>
                        <p v-else class="mt-2 text-sm text-muted-foreground">
                            {{ message.message_type_label }} message
                        </p>

                        <div v-if="message.attachments.length > 0" class="mt-3 grid gap-1">
                            <a
                                v-for="attachment in message.attachments"
                                :key="attachment.id"
                                :href="downloadUrl(attachment.id)"
                                class="text-xs text-primary underline"
                            >
                                {{ attachment.original_name }} ({{ formatBytes(attachment.size) }})
                            </a>
                        </div>

                        <div v-if="inboundAttachments(message).length > 0" class="mt-3 grid gap-1">
                            <p
                                v-for="(attachment, index) in inboundAttachments(message)"
                                :key="`inbound-${message.id}-${index}`"
                                class="text-xs text-muted-foreground"
                            >
                                Received attachment ({{ attachment.type ?? 'file' }}<span v-if="attachment.file_name">: {{ attachment.file_name }}</span>) — stored as metadata only
                            </p>
                        </div>

                        <p v-if="message.failure_reason" class="mt-2 text-xs text-destructive">
                            {{ message.failure_reason }}
                        </p>

                        <div v-if="message.is_retryable && props.permissions.manage" class="mt-2">
                            <Button size="sm" variant="outline" @click="retryMessage(message)">Retry sending</Button>
                        </div>
                    </div>
                </div>

                <div v-if="props.permissions.manage" class="mt-6 rounded-lg border p-4">
                    <h3 class="text-sm font-medium mb-2">Send message</h3>

                    <div
                        v-if="!props.communication.usable"
                        class="rounded-md border border-yellow-300 bg-yellow-50 p-3 text-xs text-yellow-900"
                    >
                        Sending is unavailable: {{ props.communication.error_message ?? 'the connected account is not ready.' }}
                    </div>

                    <div
                        v-else-if="!canSendText"
                        class="rounded-md border border-yellow-300 bg-yellow-50 p-3 text-xs text-yellow-900"
                    >
                        {{ props.communication.capability_reasons.outbound_text ?? 'This provider cannot send messages.' }}
                    </div>

                    <div v-else class="grid gap-2">
                        <textarea
                            v-model="composerBody"
                            rows="3"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder="Type a message..."
                        ></textarea>

                        <div v-if="canAttach">
                            <Input
                                ref="attachmentInput"
                                type="file"
                                multiple
                                @change="onFilesSelected"
                            />
                            <p class="mt-1 text-xs text-muted-foreground">
                                Up to {{ props.communication.attachments.max_per_message }} files, {{ props.communication.attachments.max_size_kb }} KB each.
                            </p>
                        </div>

                        <p v-else-if="attachmentsReason" class="text-xs text-muted-foreground">
                            {{ attachmentsReason }}
                        </p>

                        <p v-if="composerError" class="text-xs text-destructive">{{ composerError }}</p>

                        <div class="flex gap-2">
                            <Button size="sm" @click="sendMessage" :disabled="!canSubmit">
                                {{ sending ? 'Queueing...' : 'Send' }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h2 class="text-lg font-semibold mb-4">Details</h2>

                <div class="rounded-lg border p-4 mb-4">
                    <h3 class="text-sm font-medium mb-2">Contact</h3>
                    <p v-if="conversation.contact" class="text-sm">{{ conversation.contact.name }}</p>
                    <p v-else class="text-sm text-muted-foreground">No contact linked.</p>
                    <p v-if="conversation.contact?.email" class="text-xs text-muted-foreground">{{ conversation.contact.email }}</p>
                    <p v-if="conversation.contact?.phone" class="text-xs text-muted-foreground">{{ conversation.contact.phone }}</p>
                </div>

                <div class="rounded-lg border p-4 mb-4">
                    <h3 class="text-sm font-medium mb-2">Connected account</h3>
                    <p v-if="conversation.connected_account" class="text-sm">{{ conversation.connected_account.account_name }}</p>
                    <p v-else class="text-sm text-muted-foreground">No connected account linked.</p>
                    <p class="text-xs text-muted-foreground">{{ conversation.connected_account?.provider_label ?? conversation.provider }}</p>
                    <p class="mt-2 text-xs" :class="props.communication.usable ? 'text-green-700' : 'text-destructive'">
                        {{ props.communication.usable ? 'Ready to send' : 'Not ready to send' }}
                    </p>
                </div>

                <div class="rounded-lg border p-4">
                    <h3 class="text-sm font-medium mb-2">Communication capabilities</h3>
                    <ul class="grid gap-1 text-xs text-muted-foreground">
                        <li v-for="(enabled, capability) in props.communication.capabilities" :key="capability">
                            {{ enabled ? '✓' : '✕' }} {{ String(capability).replace(/_/g, ' ') }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
