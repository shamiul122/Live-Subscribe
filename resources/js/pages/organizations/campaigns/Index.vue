<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    CampaignFilters,
    CampaignOption,
    CampaignOrganization,
    CampaignPermissions,
    CampaignRecord,
    CampaignStatusValue,
} from '@/types';

const props = defineProps<{
    organization: CampaignOrganization;
    campaigns: CampaignRecord[];
    filters: CampaignFilters;
    statuses: CampaignOption[];
    types: CampaignOption[];
    channels: CampaignOption[];
    permissions: CampaignPermissions;
}>();

const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');
const typeFilter = ref(props.filters.type ?? '');
const channelFilter = ref(props.filters.channel ?? '');

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

function submitFilters(): void {
    const query: Record<string, string> = {};

    if (search.value !== '') {
        query.search = search.value;
    }

    if (statusFilter.value !== '') {
        query.status = statusFilter.value;
    }

    if (typeFilter.value !== '') {
        query.type = typeFilter.value;
    }

    if (channelFilter.value !== '') {
        query.channel = channelFilter.value;
    }

    router.get(`/organizations/${props.organization.slug}/campaigns`, query, {
        preserveScroll: true,
        preserveState: true,
    });
}

function resetFilters(): void {
    search.value = '';
    statusFilter.value = '';
    typeFilter.value = '';
    channelFilter.value = '';

    router.get(
        `/organizations/${props.organization.slug}/campaigns`,
        {},
        { preserveScroll: true },
    );
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
    <Head title="Campaigns" />

    <div class="p-6">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Marketing Campaigns</h1>

            <Button
                v-if="props.permissions.manage"
                size="sm"
                @click="
                    router.visit(
                        `/organizations/${props.organization.slug}/campaigns/create`,
                    )
                "
            >
                New campaign
            </Button>
        </div>

        <p class="text-muted-foreground mb-6 text-sm">
            Plan and organise marketing campaigns for this organization. A
            campaign only describes intent; no message is sent in this step.
        </p>

        <form
            class="mb-6 flex flex-wrap items-end gap-3"
            @submit.prevent="submitFilters"
        >
            <div class="grid gap-2">
                <Label for="campaign-search">Search</Label>
                <Input
                    id="campaign-search"
                    v-model="search"
                    placeholder="Name or description"
                />
            </div>

            <div class="grid gap-2">
                <Label for="campaign-status">Status</Label>
                <select
                    id="campaign-status"
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

            <div class="grid gap-2">
                <Label for="campaign-type">Type</Label>
                <select
                    id="campaign-type"
                    v-model="typeFilter"
                    class="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                >
                    <option value="">All types</option>
                    <option
                        v-for="type in props.types"
                        :key="type.value"
                        :value="type.value"
                    >
                        {{ type.label }}
                    </option>
                </select>
            </div>

            <div class="grid gap-2">
                <Label for="campaign-channel">Channel</Label>
                <select
                    id="campaign-channel"
                    v-model="channelFilter"
                    class="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                >
                    <option value="">All channels</option>
                    <option
                        v-for="channel in props.channels"
                        :key="channel.value"
                        :value="channel.value"
                    >
                        {{ channel.label }}
                    </option>
                </select>
            </div>

            <div class="flex gap-2">
                <Button type="submit" size="sm">Search</Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="resetFilters"
                >
                    Reset
                </Button>
            </div>
        </form>

        <div
            v-if="props.campaigns.length === 0"
            class="text-muted-foreground rounded-lg border p-6 text-sm"
        >
            No campaigns found. Create a campaign to get started.
        </div>

        <div v-else class="grid gap-3">
            <div
                v-for="campaign in props.campaigns"
                :key="campaign.id"
                class="rounded-lg border p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-medium">
                            <Link
                                :href="`/organizations/${props.organization.slug}/campaigns/${campaign.id}`"
                                class="hover:underline"
                            >
                                {{ campaign.name }}
                            </Link>
                        </h3>
                        <p class="text-muted-foreground text-xs">
                            {{ campaign.type_label }}
                            <span v-if="campaign.channel">
                                • {{ campaign.channel }}
                            </span>
                            <span v-if="campaign.connected_account">
                                •
                                {{ campaign.connected_account.account_name }}
                            </span>
                        </p>
                        <p
                            v-if="campaign.description"
                            class="text-muted-foreground mt-1 text-sm"
                        >
                            {{ campaign.description }}
                        </p>
                        <p class="text-muted-foreground mt-2 text-xs">
                            Audience: {{ campaign.audience_size }} • Recipients:
                            {{ campaign.recipients_count ?? 0 }} • Created:
                            {{ formatDate(campaign.created_at) }}
                        </p>
                    </div>

                    <div class="flex flex-col items-end gap-2">
                        <span
                            class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium"
                            :class="statusClass(campaign.status)"
                        >
                            {{ campaign.status_label }}
                        </span>

                        <div class="flex gap-2">
                            <Link
                                :href="`/organizations/${props.organization.slug}/campaigns/${campaign.id}/recipients`"
                            >
                                <Button size="sm" variant="outline">
                                    Recipients
                                </Button>
                            </Link>
                            <Link
                                v-if="
                                    props.permissions.manage &&
                                    campaign.can.edit
                                "
                                :href="`/organizations/${props.organization.slug}/campaigns/${campaign.id}/edit`"
                            >
                                <Button size="sm" variant="outline"
                                    >Edit</Button
                                >
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
