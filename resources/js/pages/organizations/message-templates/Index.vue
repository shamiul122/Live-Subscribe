<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    MessageTemplateFilters,
    MessageTemplateOption,
    MessageTemplatePermissions,
    MessageTemplateRecord,
    MessageTemplateStatusValue,
    TemplateOrganization,
} from '@/types';

const props = defineProps<{
    organization: TemplateOrganization;
    templates: MessageTemplateRecord[];
    filters: MessageTemplateFilters;
    categories: MessageTemplateOption[];
    statuses: MessageTemplateOption[];
    channels: MessageTemplateOption[];
    permissions: MessageTemplatePermissions;
}>();

const search = ref(props.filters.search ?? '');
const channelFilter = ref(props.filters.channel ?? '');
const categoryFilter = ref(props.filters.category ?? '');
const statusFilter = ref(props.filters.status ?? '');

function statusClass(status: MessageTemplateStatusValue): string {
    switch (status) {
        case 'active':
            return 'bg-green-100 text-green-800';
        case 'archived':
            return 'bg-gray-200 text-gray-700';
        case 'draft':
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function channelLabel(channel: string | null): string {
    if (!channel) {
        return 'Any channel';
    }

    return (
        props.channels.find((option) => option.value === channel)?.label ??
        channel
    );
}

function submitFilters(): void {
    const query: Record<string, string> = {};

    if (search.value !== '') {
        query.search = search.value;
    }

    if (channelFilter.value !== '') {
        query.channel = channelFilter.value;
    }

    if (categoryFilter.value !== '') {
        query.category = categoryFilter.value;
    }

    if (statusFilter.value !== '') {
        query.status = statusFilter.value;
    }

    router.get(
        `/organizations/${props.organization.slug}/message-templates`,
        query,
        { preserveScroll: true, preserveState: true },
    );
}

function resetFilters(): void {
    search.value = '';
    channelFilter.value = '';
    categoryFilter.value = '';
    statusFilter.value = '';

    router.get(
        `/organizations/${props.organization.slug}/message-templates`,
        {},
        { preserveScroll: true },
    );
}

function archiveTemplate(id: number): void {
    router.post(
        `/organizations/${props.organization.slug}/message-templates/${id}/archive`,
        {},
        { preserveScroll: true },
    );
}

function restoreTemplate(id: number): void {
    router.post(
        `/organizations/${props.organization.slug}/message-templates/${id}/restore`,
        {},
        { preserveScroll: true },
    );
}

function deleteTemplate(id: number): void {
    if (
        !window.confirm(
            'Delete this template? This action can be undone only from the database.',
        )
    ) {
        return;
    }

    router.delete(
        `/organizations/${props.organization.slug}/message-templates/${id}`,
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Message Templates" />

    <div class="p-6">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Message Templates</h1>

            <Button
                v-if="props.permissions.manage"
                size="sm"
                @click="
                    router.visit(
                        `/organizations/${props.organization.slug}/message-templates/create`,
                    )
                "
            >
                New template
            </Button>
        </div>

        <p class="text-muted-foreground mb-6 text-sm">
            Reusable message templates for this organization. Templates are
            rendered on demand and are never sent automatically.
        </p>

        <form
            class="mb-6 flex flex-wrap items-end gap-3"
            @submit.prevent="submitFilters"
        >
            <div class="grid gap-2">
                <Label for="template-search">Search</Label>
                <Input
                    id="template-search"
                    v-model="search"
                    placeholder="Name, description or content"
                />
            </div>

            <div class="grid gap-2">
                <Label for="template-filter-channel">Channel</Label>
                <select
                    id="template-filter-channel"
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

            <div class="grid gap-2">
                <Label for="template-filter-category">Category</Label>
                <select
                    id="template-filter-category"
                    v-model="categoryFilter"
                    class="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                >
                    <option value="">All categories</option>
                    <option
                        v-for="category in props.categories"
                        :key="category.value"
                        :value="category.value"
                    >
                        {{ category.label }}
                    </option>
                </select>
            </div>

            <div class="grid gap-2">
                <Label for="template-filter-status">Status</Label>
                <select
                    id="template-filter-status"
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
            v-if="props.templates.length === 0"
            class="text-muted-foreground rounded-lg border p-6 text-sm"
        >
            No message templates found. Create a template to get started.
        </div>

        <div v-else class="grid gap-3">
            <div
                v-for="template in props.templates"
                :key="template.id"
                class="rounded-lg border p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-medium">
                            <Link
                                :href="`/organizations/${props.organization.slug}/message-templates/${template.id}`"
                                class="hover:underline"
                            >
                                {{ template.name }}
                            </Link>
                        </h3>
                        <p class="text-muted-foreground text-xs">
                            {{ template.category_label }} •
                            {{ channelLabel(template.channel) }}
                        </p>
                        <p
                            v-if="template.description"
                            class="text-muted-foreground mt-1 text-sm"
                        >
                            {{ template.description }}
                        </p>
                        <p class="text-muted-foreground mt-2 text-xs">
                            Variables: {{ template.variables.length }} •
                            Updated:
                            {{
                                template.updated_at
                                    ? new Date(
                                          template.updated_at,
                                      ).toLocaleString()
                                    : '—'
                            }}
                        </p>
                    </div>

                    <div class="flex flex-col items-end gap-2">
                        <span
                            class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium"
                            :class="statusClass(template.status)"
                        >
                            {{ template.status_label }}
                        </span>

                        <div class="flex flex-wrap justify-end gap-2">
                            <Link
                                :href="`/organizations/${props.organization.slug}/message-templates/${template.id}/preview`"
                            >
                                <Button size="sm" variant="outline">
                                    Preview
                                </Button>
                            </Link>
                            <Link
                                v-if="
                                    props.permissions.manage &&
                                    template.is_editable
                                "
                                :href="`/organizations/${props.organization.slug}/message-templates/${template.id}/edit`"
                            >
                                <Button size="sm" variant="outline"
                                    >Edit</Button
                                >
                            </Link>
                            <template v-if="props.permissions.manage">
                                <Button
                                    v-if="template.status !== 'archived'"
                                    size="sm"
                                    variant="outline"
                                    @click="archiveTemplate(template.id)"
                                >
                                    Archive
                                </Button>
                                <Button
                                    v-else
                                    size="sm"
                                    variant="outline"
                                    @click="restoreTemplate(template.id)"
                                >
                                    Restore
                                </Button>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    @click="deleteTemplate(template.id)"
                                >
                                    Delete
                                </Button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
