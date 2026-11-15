<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MessageTemplateForm from '@/components/templates/MessageTemplateForm.vue';
import type {
    MessageTemplateOption,
    TemplateOrganization,
    TemplateVariableDefinition,
} from '@/types';

const props = defineProps<{
    organization: TemplateOrganization;
    categories: MessageTemplateOption[];
    statuses: MessageTemplateOption[];
    channels: MessageTemplateOption[];
    variables: TemplateVariableDefinition[];
}>();
</script>

<template>
    <Head title="New Message Template" />

    <div class="mx-auto max-w-3xl p-6">
        <div class="mb-6">
            <Link
                :href="`/organizations/${props.organization.slug}/message-templates`"
                class="text-muted-foreground text-sm hover:underline"
            >
                ← Back to message templates
            </Link>
            <h1 class="mt-2 text-2xl font-semibold">New message template</h1>
            <p class="text-muted-foreground mt-1 text-sm">
                Build a reusable template. Nothing is sent from this screen.
            </p>
        </div>

        <MessageTemplateForm
            :organization="props.organization"
            :categories="props.categories"
            :statuses="props.statuses"
            :channels="props.channels"
            :variables="props.variables"
        />
    </div>
</template>
