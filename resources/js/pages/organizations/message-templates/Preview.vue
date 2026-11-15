<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, reactive, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { xsrfToken } from '@/lib/csrf';
import type {
    MessageTemplatePreviewResult,
    MessageTemplateRecord,
    TemplateOrganization,
    TemplateVariableDefinition,
} from '@/types';

const props = defineProps<{
    organization: TemplateOrganization;
    template: MessageTemplateRecord;
    variables: TemplateVariableDefinition[];
    sample_values: Record<string, string>;
}>();

const usedVariables = props.template.variables.length
    ? props.template.variables
    : Object.keys(props.sample_values);

const values = reactive<Record<string, string>>({});

for (const key of usedVariables) {
    values[key] = props.sample_values[key] ?? '';
}

const result = ref<MessageTemplatePreviewResult | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

let timer: ReturnType<typeof setTimeout> | null = null;

async function renderPreview(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        const response = await fetch(
            `/organizations/${props.organization.slug}/message-templates/preview`,
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
                    subject: props.template.subject,
                    content: props.template.content,
                    values,
                }),
            },
        );

        if (!response.ok) {
            result.value = null;
            error.value = 'Unable to render the preview.';

            return;
        }

        result.value = (await response.json()) as MessageTemplatePreviewResult;
    } catch {
        result.value = null;
        error.value = 'Unable to render the preview.';
    } finally {
        loading.value = false;
    }
}

function scheduleRender(): void {
    if (timer !== null) {
        clearTimeout(timer);
    }

    timer = setTimeout(() => {
        void renderPreview();
    }, 300);
}

watch(values, scheduleRender);

onMounted(() => {
    void renderPreview();
});

function variableLabel(key: string): string {
    return (
        props.variables.find((variable) => variable.key === key)?.label ?? key
    );
}
</script>

<template>
    <Head :title="`Preview ${props.template.name}`" />

    <div class="mx-auto max-w-3xl p-6">
        <div class="mb-6">
            <Link
                :href="`/organizations/${props.organization.slug}/message-templates/${props.template.id}`"
                class="text-muted-foreground text-sm hover:underline"
            >
                ← Back to template
            </Link>
            <h1 class="mt-2 text-2xl font-semibold">
                Preview {{ props.template.name }}
            </h1>
            <p class="text-muted-foreground mt-1 text-sm">
                Values below are samples. Adjust them to see how the message
                renders. Nothing is sent.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-lg border p-4">
                <h2 class="text-sm font-semibold">Sample values</h2>

                <p
                    v-if="usedVariables.length === 0"
                    class="text-muted-foreground mt-2 text-sm"
                >
                    This template has no variables.
                </p>

                <div v-else class="mt-3 grid gap-3">
                    <div
                        v-for="key in usedVariables"
                        :key="key"
                        class="grid gap-1"
                    >
                        <Label :for="`sample-${key}`">{{
                            variableLabel(key)
                        }}</Label>
                        <Input :id="`sample-${key}`" v-model="values[key]" />
                    </div>
                </div>
            </div>

            <div class="rounded-lg border p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold">Rendered message</h2>
                    <span v-if="loading" class="text-muted-foreground text-xs">
                        Rendering…
                    </span>
                </div>

                <p v-if="error" class="mt-2 text-sm text-red-600">
                    {{ error }}
                </p>

                <template v-else-if="result">
                    <p v-if="result.subject" class="mt-3 text-sm font-medium">
                        {{ result.subject }}
                    </p>
                    <pre
                        class="bg-muted/40 mt-3 overflow-x-auto rounded-md p-3 text-sm whitespace-pre-wrap"
                        >{{ result.content }}</pre>
                </template>
            </div>
        </div>
    </div>
</template>
