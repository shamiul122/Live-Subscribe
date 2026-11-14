<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { ConnectedAccountSummary, BillingOrganizationPermissions } from '@/types';

const props = defineProps<{
    connected_accounts: ConnectedAccountSummary[];
    permissions: BillingOrganizationPermissions;
    organization: { id: number; name: string; slug: string };
}>();

const page = usePage();

function statusBadgeClass(account: ConnectedAccountSummary): string {
    switch (account.status) {
        case 'active':
            return 'bg-green-100 text-green-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'revoked':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function connect(): void {
    // Placeholder - real OAuth start handled by manager/controller flow.
}

function disconnect(account: ConnectedAccountSummary): void {
    if (window.confirm('Disconnect this social account?')) {
        router.delete(`/organizations/${props.organization.slug}/connected-accounts/${account.id}`);
    }
}
</script>

<template>
    <Head title="Connected Accounts" />

    <div class="p-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-semibold">Connected Accounts</h1>

            <Button
                v-if="props.permissions.manage"
                size="sm"
                @click="connect"
            >
                Connect account
            </Button>
        </div>

        <p class="text-sm text-muted-foreground mb-6">
            Manage the external social and communication accounts linked to this
            organization. Access tokens are never displayed here.
        </p>

        <div
            v-if="props.connected_accounts.length === 0"
            class="rounded-lg border p-6 text-sm text-muted-foreground"
        >
            No connected accounts yet. Connect a provider to get started.
        </div>

        <div v-else class="grid gap-4">
            <div
                v-for="account in props.connected_accounts"
                :key="account.id"
                class="rounded-lg border p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-medium">{{ account.account_name }}</h3>
                        <p class="text-xs text-muted-foreground">
                            {{ account.provider_label }}
                            <span v-if="account.account_username">• @{{ account.account_username }}</span>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Status: {{ account.status_label }}
                        </p>
                        <p v-if="account.token_expires_at" class="text-xs text-muted-foreground">
                            Token expires: {{ new Date(account.token_expires_at).toLocaleDateString() }}
                        </p>
                        <p v-if="account.last_synced_at" class="text-xs text-muted-foreground">
                            Last synced: {{ new Date(account.last_synced_at).toLocaleString() }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-sm">{{ account.is_usable ? 'usable' : 'not usable' }}</span>

                        <Button
                            v-if="props.permissions.manage"
                            size="sm"
                            variant="destructive"
                            @click="disconnect(account)"
                        >
                            Disconnect
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
