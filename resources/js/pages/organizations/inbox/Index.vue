<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import type { ConversationPriorityValue, ConversationStatusValue, InboxContactSummary, InboxConversationSummary, InboxPermissions, InboxProvider } from '@/types';

const props = defineProps<{
    conversations: InboxConversationSummary[];
    contacts: InboxContactSummary[];
    statuses: ConversationStatusValue[];
    priorities: ConversationPriorityValue[];
    providers: InboxProvider[];
    permissions: InboxPermissions;
    organization: { id: number; name: string; slug: string };
}>();

const search = ref('');
const statusFilter = ref('');
const providerFilter = ref('');

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

function conversationHref(conversation: InboxConversationSummary): string {
    return `/organizations/${props.organization.slug}/inbox/conversations/${conversation.id}`;
}

function submitSearch(): void {
    const query: Record<string, string> = {};

    if (search.value !== '') {
        query.search = search.value;
    }

    if (statusFilter.value !== '') {
        query.status = statusFilter.value;
    }

    if (providerFilter.value !== '') {
        query.provider = providerFilter.value;
    }

    router.get(`/organizations/${props.organization.slug}/inbox`, query, { preserveScroll: true });
}

function resetFilters(): void {
    search.value = '';
    statusFilter.value = '';
    providerFilter.value = '';

    router.get(`/organizations/${props.organization.slug}/inbox`, {}, { preserveScroll: true });
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
    <Head title="Inbox" />

    <div class="p-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold">Unified Inbox</h1>
        </div>

        <p class="text-sm text-muted-foreground mb-6">
            All conversations of this organization across connected social and
            communication accounts.
        </p>

        <form @submit.prevent="submitSearch" class="flex flex-wrap items-end gap-3 mb-6">
            <div class="grid gap-2">
                <Label for="inbox-search">Search</Label>
                <Input
                    id="inbox-search"
                    v-model="search"
                    placeholder="Contact, subject or message"
                />
            </div>

            <div class="grid gap-2">
                <Label for="inbox-status">Status</Label>
                <select
                    id="inbox-status"
                    v-model="statusFilter"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                    <option value="">All statuses</option>
                    <option v-for="status in props.statuses" :key="status" :value="status">{{ status }}</option>
                </select>
            </div>

            <div class="grid gap-2">
                <Label for="inbox-provider">Provider</Label>
                <select
                    id="inbox-provider"
                    v-model="providerFilter"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                    <option value="">All providers</option>
                    <option v-for="provider in props.providers" :key="provider" :value="provider">{{ provider }}</option>
                </select>
            </div>

            <div class="flex gap-2">
                <Button type="submit" size="sm">Search</Button>
                <Button type="button" variant="outline" size="sm" @click="resetFilters">Reset</Button>
            </div>
        </form>

        <div
            v-if="props.conversations.length === 0"
            class="rounded-lg border p-6 text-sm text-muted-foreground"
        >
            No conversations found. Conversations appear here when a connected
            account sends a message or a conversation is created.
        </div>

        <div v-else class="grid gap-3">
            <Link
                v-for="conversation in props.conversations"
                :key="conversation.id"
                :href="conversationHref(conversation)"
                class="block rounded-lg border p-4 hover:bg-accent transition-colors"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-medium">
                            {{ conversation.contact?.name ?? conversation.subject ?? 'Conversation' }}
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            {{ conversation.connected_account?.provider_label ?? conversation.provider }}
                            <span v-if="conversation.connected_account?.account_name"> • {{ conversation.connected_account.account_name }}</span>
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ conversation.last_message_preview ?? 'No messages yet.' }}
                        </p>
                    </div>

                    <div class="flex flex-col items-end gap-2">
                        <span
                            class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium"
                            :class="statusClass(conversation.status)"
                        >
                            {{ conversation.status_label }}
                        </span>

                        <Badge v-if="conversation.unread_count > 0" variant="destructive">
                            {{ conversation.unread_count }} unread
                        </Badge>
                    </div>
                </div>

                <div class="mt-2 flex items-center gap-3 text-xs text-muted-foreground">
                    <span>Last activity: {{ formatDate(conversation.last_message_at) }}</span>
                    <span>•</span>
                    <span>{{ conversation.priority_label }} priority</span>
                </div>
            </Link>
        </div>
    </div>
</template>
