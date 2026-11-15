<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { OrderSummary, PlanSummary } from '@/types';

const props = defineProps<{
    organization: { id: number; name: string; slug: string };
    order: OrderSummary;
    plan: PlanSummary | null;
    gateway: string;
    mock_payment: boolean;
    mock_enabled: boolean;
}>();

const base = `/organizations/${props.organization.slug}`;

function confirm(outcome: 'success' | 'failure'): void {
    router.post(`${base}/checkout/${props.order.id}`, { outcome });
}
</script>

<template>
    <Head title="Checkout" />

    <div class="mx-auto max-w-xl p-6">
        <div class="mb-6">
            <Link
                :href="`${base}/subscription`"
                class="text-muted-foreground text-sm hover:underline"
            >
                ← Back to subscription
            </Link>
            <h1 class="mt-2 text-2xl font-semibold">Checkout</h1>
            <p class="text-muted-foreground mt-1 text-sm">
                Order #{{ props.order.id }} for {{ props.organization.name }}.
            </p>
        </div>

        <div class="rounded-lg border p-4">
            <div class="flex items-center justify-between">
                <span class="text-muted-foreground text-sm">Plan</span>
                <span class="font-medium">{{ props.plan?.name ?? '—' }}</span>
            </div>
            <div class="mt-2 flex items-center justify-between">
                <span class="text-muted-foreground text-sm">Amount</span>
                <span class="font-medium">{{
                    props.order.amount.formatted
                }}</span>
            </div>
            <div class="mt-2 flex items-center justify-between">
                <span class="text-muted-foreground text-sm">Status</span>
                <span class="font-medium">{{ props.order.status_label }}</span>
            </div>
            <div class="mt-2 flex items-center justify-between">
                <span class="text-muted-foreground text-sm">Gateway</span>
                <span class="font-medium">{{ props.gateway }}</span>
            </div>
        </div>

        <p class="text-muted-foreground mt-4 text-xs">
            This is a
            {{ props.mock_payment ? 'mock' : 'live' }} payment flow. No real
            charge is made in mock mode.
        </p>

        <div class="mt-6 flex flex-wrap gap-2">
            <Button :disabled="!props.mock_payment" @click="confirm('success')">
                Pay {{ props.order.amount.formatted }}
            </Button>
            <Button variant="outline" @click="confirm('failure')">
                Simulate failure
            </Button>
        </div>

        <p v-if="!props.mock_payment" class="mt-3 text-sm text-amber-600">
            Live gateway checkout is not configured in this environment.
        </p>
    </div>
</template>
