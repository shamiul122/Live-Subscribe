<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { xsrfToken } from '@/lib/csrf';
import type {
    CampaignConnectedAccountOption,
    CampaignContactSummary,
    CampaignFormData,
    CampaignOption,
    CampaignOrganization,
    CampaignRecord,
} from '@/types';

const props = defineProps<{
    organization: CampaignOrganization;
    types: CampaignOption[];
    channels: CampaignOption[];
    audienceTypes: CampaignOption[];
    connectedAccounts: CampaignConnectedAccountOption[];
    contacts: CampaignContactSummary[];
    campaign?: CampaignRecord | null;
}>();

function initialScheduledAt(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    return value.slice(0, 16);
}

function initialContactIds(): number[] {
    const config = props.campaign?.audience_config;

    if (!config || !Array.isArray(config.contact_ids)) {
        return [];
    }

    return config.contact_ids.map((id) => Number(id));
}

const form = useForm<CampaignFormData>({
    name: props.campaign?.name ?? '',
    description: props.campaign?.description ?? '',
    type: props.campaign?.type ?? props.types[0]?.value ?? 'promotional',
    channel: props.campaign?.channel ?? '',
    connected_account_id: props.campaign?.connected_account_id ?? null,
    message_content: props.campaign?.message_content ?? '',
    audience_type:
        props.campaign?.audience_type ?? props.audienceTypes[0]?.value ?? 'all',
    audience_config: {
        contact_ids: initialContactIds(),
    },
    scheduled_at: initialScheduledAt(props.campaign?.scheduled_at),
});

const contactSearch = ref('');
const previewCount = ref<number | null>(null);
const previewLoading = ref(false);
const previewError = ref<string | null>(null);

const isSelectedAudience = computed(() => form.audience_type === 'selected');

const filteredContacts = computed<CampaignContactSummary[]>(() => {
    const needle = contactSearch.value.trim().toLowerCase();

    if (needle === '') {
        return props.contacts;
    }

    return props.contacts.filter((contact) =>
        [contact.name, contact.email ?? '', contact.phone ?? '']
            .join(' ')
            .toLowerCase()
            .includes(needle),
    );
});

const audienceError = computed<string | undefined>(
    () =>
        (form.errors as Record<string, string | undefined>)[
            'audience_config.contact_ids'
        ],
);

function isContactSelected(id: number): boolean {
    return form.audience_config.contact_ids.includes(id);
}

function toggleContact(id: number): void {
    const ids = form.audience_config.contact_ids;
    const index = ids.indexOf(id);

    if (index === -1) {
        ids.push(id);
    } else {
        ids.splice(index, 1);
    }
}

function selectAllVisible(): void {
    for (const contact of filteredContacts.value) {
        if (!form.audience_config.contact_ids.includes(contact.id)) {
            form.audience_config.contact_ids.push(contact.id);
        }
    }
}

function clearSelection(): void {
    form.audience_config.contact_ids = [];
}

async function refreshPreview(): Promise<void> {
    previewLoading.value = true;
    previewError.value = null;

    try {
        const response = await fetch(
            `/organizations/${props.organization.slug}/campaigns/audience/preview`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    audience_type: form.audience_type,
                    audience_config: form.audience_config,
                }),
            },
        );

        if (!response.ok) {
            previewCount.value = null;
            previewError.value =
                response.status === 422
                    ? 'Select at least one contact to estimate the audience.'
                    : 'Unable to estimate the audience.';

            return;
        }

        const data = (await response.json()) as { count?: number };
        previewCount.value = typeof data.count === 'number' ? data.count : null;
    } catch {
        previewCount.value = null;
        previewError.value = 'Unable to estimate the audience.';
    } finally {
        previewLoading.value = false;
    }
}

watch(
    () => [form.audience_type, form.audience_config.contact_ids.length],
    () => {
        void refreshPreview();
    },
);

onMounted(() => {
    void refreshPreview();
});

function cancel(): void {
    if (props.campaign) {
        router.visit(
            `/organizations/${props.organization.slug}/campaigns/${props.campaign.id}`,
        );

        return;
    }

    router.visit(`/organizations/${props.organization.slug}/campaigns`);
}

function submit(): void {
    if (props.campaign) {
        form.put(
            `/organizations/${props.organization.slug}/campaigns/${props.campaign.id}`,
        );

        return;
    }

    form.post(`/organizations/${props.organization.slug}/campaigns`);
}
</script>

<template>
    <form class="grid gap-6" @submit.prevent="submit">
        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2 md:col-span-2">
                <Label for="campaign-name">Name</Label>
                <Input id="campaign-name" v-model="form.name" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2 md:col-span-2">
                <Label for="campaign-description">Description</Label>
                <textarea
                    id="campaign-description"
                    v-model="form.description"
                    rows="3"
                    class="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                />
                <InputError :message="form.errors.description" />
            </div>

            <div class="grid gap-2">
                <Label for="campaign-type">Type</Label>
                <select
                    id="campaign-type"
                    v-model="form.type"
                    class="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                >
                    <option
                        v-for="type in props.types"
                        :key="type.value"
                        :value="type.value"
                    >
                        {{ type.label }}
                    </option>
                </select>
                <InputError :message="form.errors.type" />
            </div>

            <div class="grid gap-2">
                <Label for="campaign-channel">Channel</Label>
                <select
                    id="campaign-channel"
                    v-model="form.channel"
                    class="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                >
                    <option value="">Derive from connected account</option>
                    <option
                        v-for="channel in props.channels"
                        :key="channel.value"
                        :value="channel.value"
                    >
                        {{ channel.label }}
                    </option>
                </select>
                <InputError :message="form.errors.channel" />
            </div>

            <div class="grid gap-2 md:col-span-2">
                <Label for="campaign-account">Connected account</Label>
                <select
                    id="campaign-account"
                    v-model="form.connected_account_id"
                    class="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                >
                    <option :value="null">No connected account</option>
                    <option
                        v-for="account in props.connectedAccounts"
                        :key="account.id"
                        :value="account.id"
                    >
                        {{ account.account_name }} ({{
                            account.provider_label
                        }})
                    </option>
                </select>
                <p class="text-muted-foreground text-xs">
                    The channel is derived from the selected account when left
                    empty. No message is sent in this step.
                </p>
                <InputError :message="form.errors.connected_account_id" />
            </div>

            <div class="grid gap-2 md:col-span-2">
                <Label for="campaign-message">Message content</Label>
                <textarea
                    id="campaign-message"
                    v-model="form.message_content"
                    rows="5"
                    class="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                />
                <InputError :message="form.errors.message_content" />
            </div>

            <div class="grid gap-2">
                <Label for="campaign-scheduled">Scheduled at (optional)</Label>
                <Input
                    id="campaign-scheduled"
                    v-model="form.scheduled_at"
                    type="datetime-local"
                />
                <InputError :message="form.errors.scheduled_at" />
            </div>
        </div>

        <div class="rounded-lg border p-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Audience</h2>
                <div class="text-muted-foreground text-sm">
                    <span v-if="previewLoading">Estimating…</span>
                    <span v-else-if="previewCount !== null">
                        Estimated audience:
                        <span class="text-foreground font-medium">{{
                            previewCount
                        }}</span>
                    </span>
                </div>
            </div>

            <div class="mt-4 grid max-w-xs gap-2">
                <Label for="campaign-audience-type">Audience type</Label>
                <select
                    id="campaign-audience-type"
                    v-model="form.audience_type"
                    class="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                >
                    <option
                        v-for="audienceType in props.audienceTypes"
                        :key="audienceType.value"
                        :value="audienceType.value"
                    >
                        {{ audienceType.label }}
                    </option>
                </select>
                <InputError :message="form.errors.audience_type" />
            </div>

            <p v-if="previewError" class="mt-2 text-sm text-red-600">
                {{ previewError }}
            </p>

            <div v-if="isSelectedAudience" class="mt-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div class="grid max-w-xs gap-2">
                        <Label for="campaign-contact-search"
                            >Search contacts</Label
                        >
                        <Input
                            id="campaign-contact-search"
                            v-model="contactSearch"
                            placeholder="Name, email or phone"
                        />
                    </div>

                    <div class="flex gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="selectAllVisible"
                        >
                            Select visible
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="clearSelection"
                        >
                            Clear
                        </Button>
                    </div>
                </div>

                <p class="text-muted-foreground mt-2 text-xs">
                    {{ form.audience_config.contact_ids.length }} contact(s)
                    selected.
                </p>

                <div
                    v-if="props.contacts.length === 0"
                    class="text-muted-foreground mt-3 rounded-lg border p-4 text-sm"
                >
                    No contacts available in this organization yet.
                </div>

                <div
                    v-else
                    class="mt-3 grid max-h-64 gap-2 overflow-y-auto rounded-lg border p-3"
                >
                    <label
                        v-for="contact in filteredContacts"
                        :key="contact.id"
                        class="hover:bg-accent flex items-center gap-3 rounded-md p-2"
                    >
                        <input
                            type="checkbox"
                            :checked="isContactSelected(contact.id)"
                            @change="toggleContact(contact.id)"
                        />
                        <span class="text-sm">
                            {{ contact.name }}
                            <span class="text-muted-foreground text-xs">
                                {{ contact.email ?? contact.phone ?? '' }}
                            </span>
                        </span>
                    </label>
                </div>

                <InputError :message="audienceError" />
            </div>
        </div>

        <div class="flex gap-2">
            <Button type="submit" :disabled="form.processing">
                {{ props.campaign ? 'Save changes' : 'Create campaign' }}
            </Button>
            <Button type="button" variant="outline" @click="cancel">
                Cancel
            </Button>
        </div>
    </form>
</template>
