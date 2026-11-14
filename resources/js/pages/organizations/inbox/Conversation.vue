<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ConversationPriorityValue, ConversationStatusValue, InboxConversationSummary, InboxMessage, InboxPermissions, InboxProvider } from '@/types';

const props = defineProps<{
    conversation: InboxConversationSummary;
    messages: InboxMessage[];
    statuses: ConversationStatusValue[];
    priorities: ConversationPriorityValue[];
    providers: InboxProvider[];
    permissions: InboxPermissions;
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
                            <span class="text-xs font-medium uppercase text-muted-foreground">
                                {{ message.direction_label }}
                            </span>
                            <span class="text-xs text-muted-foreground">{{ formatDate(message.sent_at) }}</span>
                        </div>

                        <p v-if="message.body" class="mt-2 text-sm whitespace-pre-wrap">{{ message.body }}</p>
                        <p v-else class="mt-2 text-sm text-muted-foreground">
                            {{ message.message_type_label }} message
                        </p>
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

                <div class="rounded-lg border p-4">
                    <h3 class="text-sm font-medium mb-2">Connected account</h3>
                    <p v-if="conversation.connected_account" class="text-sm">{{ conversation.connected_account.account_name }}</p>
                    <p v-else class="text-sm text-muted-foreground">No connected account linked.</p>
                    <p class="text-xs text-muted-foreground">{{ conversation.connected_account?.provider_label ?? conversation.provider }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
