<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { xsrfToken } from '@/lib/csrf';
import type {
    MessageTemplateFormData,
    MessageTemplateOption,
    MessageTemplatePreviewResult,
    MessageTemplateRecord,
    TemplateOrganization,
    TemplateVariableDefinition,
} from '@/types';

const props = defineProps<{
    organization: TemplateOrganization;
    categories: MessageTemplateOption[];
    statuses: MessageTemplateOption[];
    channels: MessageTemplateOption[];
    variables: TemplateVariableDefinition[];
    template?: MessageTemplateRecord | null;
}>();

const contentRef = ref<HTMLTextAreaElement | null>(null);

const form = useForm<MessageTemplateFormData>({
    name: props.template?.name ?? '',
    description: props.template?.description ?? '',
    channel: props.template?.channel ?? '',
    category:
        props.template?.category ?? props.categories[0]?.value ?? 'custom',
    status: props.template?.status ?? 'draft',
    subject: props.template?.subject ?? '',
    content: props.template?.content ?? '',
});

const preview = ref<MessageTemplatePreviewResult | null>(null);
const previewLoading = ref(false);
const previewError = ref<string | null>(null);

const detectedVariables = computed<string[]>(() => {
    const matches = form.content.matchAll(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g);
    const found = new Set<string>();

    for (const match of matches) {
        found.add(match[1]);
    }

    return [...found];
});

const unknownVariables = computed<string[]>(() =>
    detectedVariables.value.filter(
        (key) => !props.variables.some((variable) => variable.key === key),
    ),
);

function token(key: string): string {
    return '{{' + key + '}}';
}

const unknownVariableTokens = computed<string>(() =>
    unknownVariables.value.map((key) => token(key)).join(', '),
);

let previewTimer: ReturnType<typeof setTimeout> | null = null;

function insertVariable(key: string): void {
    const token = `{{${key}}}`;
    const element = contentRef.value;

    if (!element) {
        form.content = `${form.content}${token}`;

        return;
    }

    const start = element.selectionStart ?? form.content.length;
    const end = element.selectionEnd ?? form.content.length;

    form.content =
        form.content.slice(0, start) + token + form.content.slice(end);

    requestAnimationFrame(() => {
        element.focus();
        element.setSelectionRange(start + token.length, start + token.length);
    });
}

async function refreshPreview(): Promise<void> {
    if (form.content.trim() === '') {
        preview.value = null;
        previewError.value = null;

        return;
    }

    previewLoading.value = true;
    previewError.value = null;

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
                    subject: form.subject,
                    content: form.content,
                }),
            },
        );

        if (!response.ok) {
            preview.value = null;
            previewError.value =
                response.status === 422
                    ? 'Fix the unknown variables before previewing.'
                    : 'Unable to render the preview.';

            return;
        }

        preview.value = (await response.json()) as MessageTemplatePreviewResult;
    } catch {
        preview.value = null;
        previewError.value = 'Unable to render the preview.';
    } finally {
        previewLoading.value = false;
    }
}

function schedulePreview(): void {
    if (previewTimer !== null) {
        clearTimeout(previewTimer);
    }

    previewTimer = setTimeout(() => {
        void refreshPreview();
    }, 400);
}

watch(() => [form.content, form.subject], schedulePreview);

onMounted(() => {
    void refreshPreview();
});

function cancel(): void {
    if (props.template) {
        router.visit(
            `/organizations/${props.organization.slug}/message-templates/${props.template.id}`,
        );

        return;
    }

    router.visit(`/organizations/${props.organization.slug}/message-templates`);
}

function submit(): void {
    if (props.template) {
        form.put(
            `/organizations/${props.organization.slug}/message-templates/${props.template.id}`,
        );

        return;
    }

    form.post(`/organizations/${props.organization.slug}/message-templates`);
}
</script>

<template>
    <form class="grid gap-6" @submit.prevent="submit">
        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2 md:col-span-2">
                <Label for="template-name">Name</Label>
                <Input id="template-name" v-model="form.name" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2 md:col-span-2">
                <Label for="template-description">Description</Label>
                <textarea
                    id="template-description"
                    v-model="form.description"
                    rows="2"
                    class="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
                />
                <InputError :message="form.errors.description" />
            </div>

            <div class="grid gap-2">
                <Label for="template-channel">Channel</Label>
                <select
                    id="template-channel"
                    v-model="form.channel"
                    class="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                >
                    <option value="">Any channel</option>
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

            <div class="grid gap-2">
                <Label for="template-category">Category</Label>
                <select
                    id="template-category"
                    v-model="form.category"
                    class="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                >
                    <option
                        v-for="category in props.categories"
                        :key="category.value"
                        :value="category.value"
                    >
                        {{ category.label }}
                    </option>
                </select>
                <InputError :message="form.errors.category" />
            </div>

            <div class="grid gap-2">
                <Label for="template-status">Status</Label>
                <select
                    id="template-status"
                    v-model="form.status"
                    class="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                >
                    <option
                        v-for="status in props.statuses"
                        :key="status.value"
                        :value="status.value"
                    >
                        {{ status.label }}
                    </option>
                </select>
                <InputError :message="form.errors.status" />
            </div>

            <div class="grid gap-2 md:col-span-2">
                <Label for="template-subject">Subject (email)</Label>
                <Input
                    id="template-subject"
                    v-model="form.subject"
                    placeholder="Required for email templates"
                />
                <InputError :message="form.errors.subject" />
            </div>

            <div class="grid gap-2 md:col-span-2">
                <Label for="template-content">Content</Label>
                <textarea
                    id="template-content"
                    ref="contentRef"
                    v-model="form.content"
                    rows="8"
                    class="border-input bg-background flex w-full rounded-md border px-3 py-2 font-mono text-sm"
                    placeholder="Hello {{customer_name}}, ..."
                />
                <InputError :message="form.errors.content" />
            </div>
        </div>

        <div class="rounded-lg border p-4">
            <h2 class="text-sm font-semibold">Available variables</h2>
            <p class="text-muted-foreground mt-1 text-xs">
                Click a variable to insert it at the cursor. Only supported
                variables are allowed.
            </p>

            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    v-for="variable in props.variables"
                    :key="variable.key"
                    type="button"
                    class="hover:bg-accent rounded-full border px-3 py-1 text-xs"
                    :title="variable.description"
                    @click="insertVariable(variable.key)"
                >
                    {{ token(variable.key) }}
                </button>
            </div>

            <p
                v-if="unknownVariables.length > 0"
                class="mt-3 text-sm text-red-600"
            >
                Unknown variable(s):
                {{ unknownVariableTokens }}
            </p>
            <p
                v-else-if="detectedVariables.length > 0"
                class="text-muted-foreground mt-3 text-xs"
            >
                Detected: {{ detectedVariables.join(', ') }}
            </p>
        </div>

        <div class="rounded-lg border p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold">
                    Preview with sample values
                </h2>
                <span
                    v-if="previewLoading"
                    class="text-muted-foreground text-xs"
                >
                    Rendering…
                </span>
            </div>

            <p v-if="previewError" class="mt-2 text-sm text-red-600">
                {{ previewError }}
            </p>

            <div v-else-if="preview" class="mt-3 space-y-2">
                <p v-if="preview.subject" class="text-sm font-medium">
                    {{ preview.subject }}
                </p>
                <pre
                    class="bg-muted/40 overflow-x-auto rounded-md p-3 text-sm whitespace-pre-wrap"
                    >{{ preview.content }}</pre>
            </div>
            <p v-else class="text-muted-foreground mt-2 text-sm">
                Add content to see a live preview.
            </p>
        </div>

        <div class="flex gap-2">
            <Button type="submit" :disabled="form.processing">
                {{ props.template ? 'Save changes' : 'Create template' }}
            </Button>
            <Button type="button" variant="outline" @click="cancel">
                Cancel
            </Button>
        </div>
    </form>
</template>
