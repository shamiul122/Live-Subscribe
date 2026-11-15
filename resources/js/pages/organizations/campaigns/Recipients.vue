<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type {
    CampaignOption,
    CampaignOrganization,
    CampaignPermissions,
    CampaignRecipientFilters,
    CampaignRecipientStatusValue,
    CampaignRecipientSummary,
    CampaignRecord,
} from '@/types';

const props = defineProps<{
    organization: CampaignOrganization;
    campaign: CampaignRecord;
    recipients: CampaignRecipientSummary[];
    filters: CampaignRecipientFilters;
    statuses: CampaignOption[];
    permissions: CampaignPermissions;
}>();

const statusFilter = ref(props.filters.status ?? '');

function base(): string {
    return `/organizations/${props.organization.slug}/campaigns/${props.campaign.id}`;
}

function recipientStatusClass(status: CampaignRecipientStatusValue): string {
    switch (status) {
        case 'pending':
            return 'bg-gray-100 text-gray-800';
        case 'queued':
            return 'bg-blue-100 text-blue-800';
        case 'sending':
            return 'bg-indigo-100 text-indigo-800';
        case 'sent':
            return 'bg-sky-100 text-sky-800';
        case 'delivered':
            return 'bg-green-100 text-green-800';
        case 'read':
            return 'bg-emerald-100 text-emerald-800';
        case 'failed':
            return 'bg-red-100 text-red-800';
        case 'skipped':
            return 'bg-gray-200 text-gray-700';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function applyFilter(): void {
    const query: Record<string, string> = {};

    if (statusFilter.value !== '') {
        query.status = statusFilter.value;
    }

    router.get(`${base()}/recipients`, query, {
        preserveScroll: true,
        preserveState: true,
    });
}

function resetFilter(): void {
    statusFilter.value = '';
    router.get(`${base()}/recipients`, {}, { preserveScroll: true });
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
    <Head :title="`${props.campaign.name} recipients`" />

    <div class="p-6">
        <div class="mb-4">
            <Link
                :href="base()"
                class="text-muted-foreground text-sm hover:underline"
            >
                ← Back to campaign
            </Link>
            <h1 class="mt-2 text-2xl font-semibold">Recipients</h1>
            <p class="text-muted-foreground text-sm">
                {{ props.campaign.name }} • {{ props.campaign.status_label }}
            </p>
        </div>

        <p class="text-muted-foreground mb-6 text-sm">
            Recipients are materialised from the frozen audience snapshot.
            Preparing recipients does not send any message.
        </p>

        <form
            class="mb-6 flex flex-wrap items-end gap-3"
            @submit.prevent="applyFilter"
        >
            <div class="grid gap-2">
                <Label for="recipient-status">Status</Label>
                <select
                    id="recipient-status"
                    v-model="statusFilter"
                    class="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                >
                    <option value="">All statuses</option>
                    <option
                        v-for="status in props.statuses"
                        :key="status.value"
                        :value="status.value"
                    >
                        {{ status.label }}
                    </option>
                </select>
            </div>

            <div class="flex gap-2">
                <Button type="submit" size="sm">Filter</Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="resetFilter"
                >
                    Reset
                </Button>
            </div>
        </form>

        <div
            v-if="props.recipients.length === 0"
            class="text-muted-foreground rounded-lg border p-6 text-sm"
        >
            No recipients found. Prepare recipients from the campaign page to
            materialise the audience.
        </div>

        <div v-else class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-3 py-2 font-medium">Contact</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 font-medium">Delivery</th>
                        <th class="px-3 py-2 font-medium">Sent</th>
                        <th class="px-3 py-2 font-medium">Delivered</th>
                        <th class="px-3 py-2 font-medium">Read</th>
                        <th class="px-3 py-2 font-medium">Error</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="recipient in props.recipients"
                        :key="recipient.id"
                        class="border-t"
                    >
                        <td class="px-3 py-2">
                            <span class="font-medium">
                                {{ recipient.contact?.name ?? 'Unknown' }}
                            </span>
                            <span
                                v-if="recipient.contact?.email"
                                class="text-muted-foreground block text-xs"
                            >
                                {{ recipient.contact.email }}
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            <span
                                class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium"
                                :class="recipientStatusClass(recipient.status)"
                            >
                                {{ recipient.status_label }}
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            {{ recipient.delivery_status_label ?? '—' }}
                        </td>
                        <td class="px-3 py-2">
                            {{ formatDate(recipient.sent_at) }}
                        </td>
                        <td class="px-3 py-2">
                            {{ formatDate(recipient.delivered_at) }}
                        </td>
                        <td class="px-3 py-2">
                            {{ formatDate(recipient.read_at) }}
                        </td>
                        <td class="text-muted-foreground px-3 py-2">
                            {{ recipient.error_message ?? '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
