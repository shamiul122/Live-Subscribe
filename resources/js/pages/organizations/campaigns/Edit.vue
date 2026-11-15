<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import CampaignForm from '@/components/campaigns/CampaignForm.vue';
import type {
    CampaignConnectedAccountOption,
    CampaignContactSummary,
    CampaignOption,
    CampaignOrganization,
    CampaignRecord,
    ReusableMessageTemplate,
} from '@/types';

const props = defineProps<{
    organization: CampaignOrganization;
    campaign: CampaignRecord;
    types: CampaignOption[];
    channels: CampaignOption[];
    audience_types: CampaignOption[];
    connected_accounts: CampaignConnectedAccountOption[];
    contacts: CampaignContactSummary[];
    templates: ReusableMessageTemplate[];
}>();
</script>

<template>
    <Head :title="`Edit ${props.campaign.name}`" />

    <div class="p-6">
        <div class="mb-4">
            <Link
                :href="`/organizations/${props.organization.slug}/campaigns/${props.campaign.id}`"
                class="text-muted-foreground text-sm hover:underline"
            >
                ← Back to campaign
            </Link>
            <h1 class="mt-2 text-2xl font-semibold">Edit campaign</h1>
            <p class="text-muted-foreground text-sm">
                Saving rebuilds and re-freezes the audience snapshot.
            </p>
        </div>

        <CampaignForm
            :organization="props.organization"
            :types="props.types"
            :channels="props.channels"
            :audience-types="props.audience_types"
            :connected-accounts="props.connected_accounts"
            :contacts="props.contacts"
            :templates="props.templates"
            :campaign="props.campaign"
        />
    </div>
</template>
