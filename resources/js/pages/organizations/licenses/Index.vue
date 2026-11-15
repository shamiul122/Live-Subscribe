<script setup lang="ts">
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { LicenseSummary, OrganizationPermissions } from '@/types';

const props = defineProps<{
    licenses: LicenseSummary[];
    permissions: OrganizationPermissions;
    organization: { id: number; name: string; slug: string };
    plain_key?: string;
    capacity?: number;
    has_subscription: boolean;
}>();

const page = usePage();
const isOwner = computed(
    () => page.props.auth.user.id === (page.props.organization as { id: number }).id,
);

const form = useForm({
    label: '',
});

function rotateLicense(licenseId: number): void {
    if (!isOwner.value) {
        alert('Only the organization owner can rotate licenses.');
        return;
    }
    if (window.confirm('Rotate the license key? The new key will be displayed once.')) {
        router.post(`/organizations/${(page.props.organization as { slug: string }).slug}/licenses/${licenseId}/rotate`);
    }
}

function revokeLicense(licenseId: number): void {
    if (!isOwner.value) {
        alert('Only the organization owner can revoke licenses.');
        return;
    }
    if (window.confirm('Revoke this license key? This action cannot be undone.')) {
        router.delete(`/organizations/${(page.props.organization as { slug: string }).slug}/licenses/${licenseId}`);
    }
}

function issueLicense(): void {
    if (!isOwner.value) {
        alert('Only the organization owner can issue licenses.');
        return;
    }

    if (form.label.trim().length === 0) {
        alert('Please enter a label for the license.');
        return;
    }

    form.post(`/organizations/${(page.props.organization as { slug: string }).slug}/licenses`);
}
</script>

<template>
    <div class="p-6">
        <Head title="Licenses" />

        <h1 class="text-2xl font-semibold mb-4">License Management</h1>

        <p class="text-sm text-muted-foreground mb-6">
            Manage license keys for this organization. Only the raw key is displayed
            once after issuance or rotation.
        </p>

        <div v-if="props.plain_key" class="rounded-lg border p-4 mb-6 bg-blue-50 border-blue-200">
            <p class="text-sm font-medium text-blue-900">License key</p>
            <p class="mt-1 break-all">
                {{ props.plain_key }}
            </p>
            <p class="text-xs text-muted-foreground">
                The key above was just issued or rotated. Please copy it now — it will not be shown again.
            </p>
        </div>

        <div v-if="props.licenses.length === 0" class="rounded-lg border p-6 text-sm text-muted-foreground">
            No licenses found. Issue a new license below.
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2">
            <div
                v-for="license in props.licenses"
                :key="license.id"
                class="rounded-lg border p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-medium">
                            {{ license.label || 'License #' + license.id }}
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Status: {{ license.status_label }}
                        </p>
                        <p v-if="license.expires_at" class="text-xs text-muted-foreground">
                            Expires: {{ new Date(license.expires_at).toLocaleDateString() }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-sm">{{ license.masked_key }}</span>

                        <Button
                            v-if="isOwner"
                            size="sm"
                            variant="outline"
                            @click="rotateLicense(license.id)"
                        >
                            Rotate
                        </Button>

                        <Button
                            v-if="isOwner"
                            size="sm"
                            variant="destructive"
                            @click="revokeLicense(license.id)"
                        >
                            Revoke
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="isOwner" class="mt-3 p-3 rounded-lg border">
            <p class="text-xs text-muted-foreground">Key:</p>
            <div class="mt-1 break-all">License key displayed once above</div>
        </div>

        <div class="mt-6 p-4 rounded-lg border border-blue-200 bg-blue-50">
            <p class="text-sm font-medium text-blue-900">Issue new license</p>

            <form
                class="mt-3 space-y-4"
                @submit.prevent="issueLicense"
            >
                <div>
                    <Label for="license-label">Label (optional)</Label>
                    <Input
                        id="license-label"
                        v-model="form.label"
                        placeholder="e.g. Production license"
                    />
                    <InputError :message="form.errors.label" />
                </div>

                <Button type="submit" :disabled="form.label.length === 0">
                    Issue license
                </Button>
            </form>
        </div>
    </div>
</template>