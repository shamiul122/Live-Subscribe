<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type {
    CampaignOrganization,
    CampaignPermissions,
    CampaignRecord,
    CampaignStatusValue,
} from '@/types';

const props = defineProps<{
    organization: CampaignOrganization;
    campaign: CampaignRecord;
    permissions: CampaignPermissions;
}>();

const canManage = computed(() => props.permissions.manage);

function statusClass(status: CampaignStatusValue): string {
    switch (status) {
        case 'draft':
            return 'bg-gray-100 text-gray-800';
        case 'ready':
            return 'bg-blue-100 text-blue-800';
        case 'scheduled':
            return 'bg-indigo-100 text-indigo-800';
        case 'running':
            return 'bg-green-100 text-green-800';
        case 'paused':
            return 'bg-yellow-100 text-yellow-800';
        case 'completed':
            return 'bg-emerald-100 text-emerald-800';
        case 'cancelled':
            return 'bg-gray-200 text-gray-700';
        case 'failed':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
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

function base(): string {
    return `/organizations/${props.organization.slug}/campaigns/${props.campaign.id}`;
}

function markReady(): void {
    router.post(`${base()}/ready`);
}

function prepareRecipients(): void {
    router.post(`${base()}/recipients/prepare`);
}

function pause(): void {
    router.post(`${base()}/pause`);
}

function resume(): void {
    router.post(`${base()}/resume`);
}

function cancel(): void {
    if (
        window.confirm(
            'Cancel this campaign? A cancelled campaign cannot be edited or restarted.',
        )
    ) {
        router.post(`${base()}/cancel`);
    }
}

function destroy(): void {
    if (window.confirm('Delete this campaign? This cannot be undone.')) {
        router.delete(base());
    }
}
</script>

<template>
    <Head :title="props.campaign.name" />

    <div class="p-6">
        <div class="mb-4">
            <Link
                :href="`/organizations/${props.organization.slug}/campaigns`"
                class="text-muted-foreground text-sm hover:underline"
            >
                ← Back to campaigns
            </Link>
        </div>

        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">
                    {{ props.campaign.name }}
                </h1>
                <p class="text-muted-foreground text-sm">
                    {{ props.campaign.type_label }}
                    <span v-if="props.campaign.channel">
                        • {{ props.campaign.channel }}
                    </span>
                </p>
            </div>

            <span
                class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium"
                :class="statusClass(props.campaign.status)"
            >
                {{ props.campaign.status_label }}
            </span>
        </div>

        <div
            v-if="canManage"
            class="mb-6 flex flex-wrap gap-2 rounded-lg border p-3"
        >
            <Button
                v-if="props.campaign.can.mark_ready"
                size="sm"
                @click="markReady"
            >
                Mark ready
            </Button>
            <Button
                v-if="props.campaign.can.prepare_recipients"
                size="sm"
                variant="outline"
                @click="prepareRecipients"
            >
                Prepare recipients
            </Button>
            <Button
                v-if="props.campaign.can.pause"
                size="sm"
                variant="outline"
                @click="pause"
            >
                Pause
            </Button>
            <Button
                v-if="props.campaign.can.resume"
                size="sm"
                variant="outline"
                @click="resume"
            >
                Resume
            </Button>
            <Button
                v-if="props.campaign.can.cancel"
                size="sm"
                variant="outline"
                @click="cancel"
            >
                Cancel campaign
            </Button>
            <Link v-if="props.campaign.can.edit" :href="`${base()}/edit`">
                <Button size="sm" variant="outline">Edit</Button>
            </Link>
            <Button
                v-if="props.campaign.can.edit"
                size="sm"
                variant="destructive"
                @click="destroy"
            >
                Delete
            </Button>
        </div>

        <p
            v-if="props.campaign.is_terminal"
            class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"
        >
            This campaign is in a terminal state and can no longer be edited or
            restarted.
        </p>

        <div class="grid gap-6 md:grid-cols-2">
            <div class="rounded-lg border p-4">
                <h2 class="mb-3 font-semibold">Details</h2>
                <dl class="grid gap-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Type</dt>
                        <dd>{{ props.campaign.type_label }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Channel</dt>
                        <dd>{{ props.campaign.channel ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Connected account</dt>
                        <dd>
                            {{
                                props.campaign.connected_account
                                    ? props.campaign.connected_account
                                          .account_name
                                    : '—'
                            }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Created by</dt>
                        <dd>{{ props.campaign.created_by?.name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Scheduled at</dt>
                        <dd>{{ formatDate(props.campaign.scheduled_at) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Started at</dt>
                        <dd>{{ formatDate(props.campaign.started_at) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Completed at</dt>
                        <dd>{{ formatDate(props.campaign.completed_at) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Cancelled at</dt>
                        <dd>{{ formatDate(props.campaign.cancelled_at) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Created</dt>
                        <dd>{{ formatDate(props.campaign.created_at) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg border p-4">
                <h2 class="mb-3 font-semibold">Audience</h2>
                <dl class="grid gap-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Audience type</dt>
                        <dd>{{ props.campaign.audience_type_label }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">
                            Estimated audience
                        </dt>
                        <dd>{{ props.campaign.audience_size }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Snapshot contacts</dt>
                        <dd>
                            {{
                                props.campaign.audience_snapshot
                                    ?.contact_count ?? '—'
                            }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Snapshot taken</dt>
                        <dd>
                            {{
                                formatDate(
                                    props.campaign.audience_snapshot
                                        ?.captured_at ?? null,
                                )
                            }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Recipients</dt>
                        <dd>{{ props.campaign.recipients_count ?? 0 }}</dd>
                    </div>
                </dl>

                <Link
                    :href="`${base()}/recipients`"
                    class="text-primary mt-4 inline-block text-sm hover:underline"
                >
                    View recipients →
                </Link>
            </div>
        </div>

        <div class="mt-6 rounded-lg border p-4">
            <h2 class="mb-3 font-semibold">Message content</h2>
            <p
                v-if="props.campaign.message_content"
                class="text-sm whitespace-pre-wrap"
            >
                {{ props.campaign.message_content }}
            </p>
            <p v-else class="text-muted-foreground text-sm">
                No message content yet.
            </p>
        </div>

        <div
            v-if="props.campaign.description"
            class="mt-6 rounded-lg border p-4"
        >
            <h2 class="mb-3 font-semibold">Description</h2>
            <p class="text-sm whitespace-pre-wrap">
                {{ props.campaign.description }}
            </p>
        </div>

        <div
            v-if="props.campaign.statistics"
            class="mt-6 rounded-lg border p-4"
        >
            <h2 class="mb-3 font-semibold">Recipient statistics</h2>
            <p
                v-if="!props.campaign.statistics.has_recipients"
                class="text-muted-foreground text-sm"
            >
                No recipients have been prepared yet.
            </p>
            <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-md border p-3">
                    <p class="text-muted-foreground text-xs">Total</p>
                    <p class="text-lg font-semibold">
                        {{ props.campaign.statistics.total }}
                    </p>
                </div>
                <div class="rounded-md border p-3">
                    <p class="text-muted-foreground text-xs">Pending</p>
                    <p class="text-lg font-semibold">
                        {{ props.campaign.statistics.pending }}
                    </p>
                </div>
                <div class="rounded-md border p-3">
                    <p class="text-muted-foreground text-xs">Sent</p>
                    <p class="text-lg font-semibold">
                        {{ props.campaign.statistics.sent }}
                    </p>
                </div>
                <div class="rounded-md border p-3">
                    <p class="text-muted-foreground text-xs">Delivered</p>
                    <p class="text-lg font-semibold">
                        {{ props.campaign.statistics.delivered }}
                    </p>
                </div>
                <div class="rounded-md border p-3">
                    <p class="text-muted-foreground text-xs">Failed</p>
                    <p class="text-lg font-semibold">
                        {{ props.campaign.statistics.failed }}
                    </p>
                </div>
                <div class="rounded-md border p-3">
                    <p class="text-muted-foreground text-xs">Skipped</p>
                    <p class="text-lg font-semibold">
                        {{ props.campaign.statistics.skipped }}
                    </p>
                </div>
                <div class="rounded-md border p-3">
                    <p class="text-muted-foreground text-xs">Delivery rate</p>
                    <p class="text-lg font-semibold">
                        {{ props.campaign.statistics.delivery_rate }}%
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
