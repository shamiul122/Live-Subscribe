<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    token: string;
    valid: boolean;
    reason: string | null;
    invitation: {
        email: string;
        role: string;
        role_label: string;
        organization_name: string | null;
        invited_by: string | null;
        expires_at: string;
    };
}>();

const form = useForm({});

function accept(): void {
    form.post(`/invitations/${props.token}`);
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
</script>

<template>
    <Head title="Organization invitation" />

    <div class="mx-auto max-w-xl space-y-6 p-6">
        <div>
            <h1 class="text-2xl font-semibold">Organization invitation</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                You have been invited to join
                {{ props.invitation.organization_name ?? 'an organization' }}.
            </p>
        </div>

        <div
            v-if="!props.valid"
            class="space-y-4 rounded-lg border border-red-200 bg-red-50 p-4"
        >
            <p class="text-sm text-red-800">
                {{ props.reason }}
            </p>
            <Button as-child>
                <Link href="/dashboard">Back to dashboard</Link>
            </Button>
        </div>

        <div v-else class="space-y-4 rounded-lg border p-4">
            <dl class="grid gap-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Organization</dt>
                    <dd class="font-medium">
                        {{ props.invitation.organization_name }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Invited email</dt>
                    <dd class="font-medium">{{ props.invitation.email }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Role</dt>
                    <dd class="font-medium">
                        {{ props.invitation.role_label }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Invited by</dt>
                    <dd class="font-medium">
                        {{ props.invitation.invited_by ?? 'Unknown' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Expires</dt>
                    <dd class="font-medium">
                        {{ formatDate(props.invitation.expires_at) }}
                    </dd>
                </div>
            </dl>

            <Button :disabled="form.processing" @click="accept">
                Accept invitation
            </Button>
        </div>
    </div>
</template>
