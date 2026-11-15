<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MessageTemplateForm from '@/components/templates/MessageTemplateForm.vue';
import type {
    MessageTemplateOption,
    MessageTemplateRecord,
    TemplateOrganization,
    TemplateVariableDefinition,
} from '@/types';

const props = defineProps<{
    organization: TemplateOrganization;
    template: MessageTemplateRecord;
    categories: MessageTemplateOption[];
    statuses: MessageTemplateOption[];
    channels: MessageTemplateOption[];
    variables: TemplateVariableDefinition[];
}>();
</script>

<template>
    <Head :title="`Edit ${props.template.name}`" />

    <div class="mx-auto max-w-3xl p-6">
        <div class="mb-6">
            <Link
                :href="`/organizations/${props.organization.slug}/message-templates/${props.template.id}`"
                class="text-muted-foreground text-sm hover:underline"
            >
                ← Back to template
            </Link>
            <h1 class="mt-2 text-2xl font-semibold">
                Edit {{ props.template.name }}
            </h1>
        </div>

        <MessageTemplateForm
            :organization="props.organization"
            :categories="props.categories"
            :statuses="props.statuses"
            :channels="props.channels"
            :variables="props.variables"
            :template="props.template"
        />
    </div>
</template>
