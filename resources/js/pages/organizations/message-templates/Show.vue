<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type {
    MessageTemplatePermissions,
    MessageTemplateRecord,
    TemplateOrganization,
    TemplateVariableDefinition,
} from '@/types';

const props = defineProps<{
    organization: TemplateOrganization;
    template: MessageTemplateRecord;
    variables: TemplateVariableDefinition[];
    permissions: MessageTemplatePermissions;
}>();

function archive(): void {
    router.post(
        `/organizations/${props.organization.slug}/message-templates/${props.template.id}/archive`,
    );
}

function restore(): void {
    router.post(
        `/organizations/${props.organization.slug}/message-templates/${props.template.id}/restore`,
    );
}

function destroy(): void {
    if (!window.confirm('Delete this message template?')) {
        return;
    }

    router.delete(
        `/organizations/${props.organization.slug}/message-templates/${props.template.id}`,
    );
}

function sampleFor(key: string): string {
    return (
        props.variables.find((variable) => variable.key === key)?.sample ?? ''
    );
}

function token(key: string): string {
    return '{{' + key + '}}';
}

const unknownVariableTokens = computed<string>(() =>
    props.template.unknown_variables.map((key) => token(key)).join(', '),
);

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}
</script>

<template>
    <Head :title="props.template.name" />

    <div class="mx-auto max-w-3xl p-6">
        <div class="mb-6">
            <Link
                :href="`/organizations/${props.organization.slug}/message-templates`"
                class="text-muted-foreground text-sm hover:underline"
            >
                ← Back to message templates
            </Link>
        </div>

        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">
                    {{ props.template.name }}
                </h1>
                <p class="text-muted-foreground mt-1 text-sm">
                    {{ props.template.category_label }} •
                    {{ props.template.channel ?? 'Any channel' }} •
                    {{ props.template.status_label }}
                </p>
            </div>

            <div class="flex flex-wrap justify-end gap-2">
                <Link
                    :href="`/organizations/${props.organization.slug}/message-templates/${props.template.id}/preview`"
                >
                    <Button size="sm" variant="outline">Preview</Button>
                </Link>
                <Link
                    v-if="
                        props.permissions.manage && props.template.is_editable
                    "
                    :href="`/organizations/${props.organization.slug}/message-templates/${props.template.id}/edit`"
                >
                    <Button size="sm">Edit</Button>
                </Link>
                <template v-if="props.permissions.manage">
                    <Button
                        v-if="props.template.status !== 'archived'"
                        size="sm"
                        variant="outline"
                        @click="archive"
                    >
                        Archive
                    </Button>
                    <Button v-else size="sm" variant="outline" @click="restore">
                        Restore
                    </Button>
                    <Button size="sm" variant="destructive" @click="destroy">
                        Delete
                    </Button>
                </template>
            </div>
        </div>

        <p
            v-if="props.template.description"
            class="text-muted-foreground mt-4 text-sm"
        >
            {{ props.template.description }}
        </p>

        <div class="mt-6 grid gap-4">
            <div v-if="props.template.subject" class="rounded-lg border p-4">
                <p class="text-muted-foreground text-xs">Subject</p>
                <p class="mt-1 font-medium">{{ props.template.subject }}</p>
            </div>

            <div class="rounded-lg border p-4">
                <p class="text-muted-foreground text-xs">Content</p>
                <pre
                    class="bg-muted/40 mt-2 overflow-x-auto rounded-md p-3 text-sm whitespace-pre-wrap"
                    >{{ props.template.content }}</pre>
            </div>

            <div class="rounded-lg border p-4">
                <p class="text-sm font-semibold">Variables</p>
                <p
                    v-if="props.template.variables.length === 0"
                    class="text-muted-foreground mt-1 text-sm"
                >
                    This template does not use any variables.
                </p>
                <ul v-else class="mt-2 grid gap-1 text-sm">
                    <li
                        v-for="key in props.template.variables"
                        :key="key"
                        class="flex items-center justify-between gap-3"
                    >
                        <code class="bg-muted rounded px-1.5 py-0.5">{{
                            token(key)
                        }}</code>
                        <span class="text-muted-foreground"
                            >e.g. {{ sampleFor(key) }}</span
                        >
                    </li>
                </ul>

                <p
                    v-if="props.template.unknown_variables.length > 0"
                    class="mt-3 text-sm text-red-600"
                >
                    Unknown:
                    {{ unknownVariableTokens }}
                </p>
            </div>

            <div class="text-muted-foreground rounded-lg border p-4 text-xs">
                Created {{ formatDate(props.template.created_at) }} • Updated
                {{ formatDate(props.template.updated_at) }}
                <span v-if="props.template.created_by">
                    • by {{ props.template.created_by.name }}
                </span>
            </div>
        </div>
    </div>
</template>
