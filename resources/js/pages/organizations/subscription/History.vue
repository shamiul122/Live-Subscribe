<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { OrderSummary } from '@/types';

const props = defineProps<{
    organization: { id: number; name: string; slug: string };
    orders: OrderSummary[];
}>();

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}
</script>

<template>
    <Head title="Payment History" />

    <div class="p-6">
        <div class="mb-6">
            <Link
                :href="`/organizations/${props.organization.slug}/subscription`"
                class="text-muted-foreground text-sm hover:underline"
            >
                ← Back to subscription
            </Link>
            <h1 class="mt-2 text-2xl font-semibold">Payment history</h1>
            <p class="text-muted-foreground mt-1 text-sm">
                Orders and transactions for {{ props.organization.name }}.
            </p>
        </div>

        <div
            v-if="props.orders.length === 0"
            class="text-muted-foreground rounded-lg border p-6 text-sm"
        >
            No orders have been placed yet.
        </div>

        <div v-else class="grid gap-3">
            <div
                v-for="order in props.orders"
                :key="order.id"
                class="rounded-lg border p-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-medium">
                            {{ order.type_label }}
                            <span v-if="order.plan">
                                • {{ order.plan.name }}</span
                            >
                        </p>
                        <p class="text-muted-foreground text-xs">
                            Order #{{ order.id }} • Created
                            {{ formatDate(order.created_at) }}
                            <span v-if="order.paid_at">
                                • Paid {{ formatDate(order.paid_at) }}</span
                            >
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="font-medium">{{ order.amount.formatted }}</p>
                        <p class="text-muted-foreground text-xs">
                            {{ order.status_label }}
                        </p>
                    </div>
                </div>

                <ul
                    v-if="order.transactions.length > 0"
                    class="mt-3 divide-y rounded-md border text-sm"
                >
                    <li
                        v-for="transaction in order.transactions"
                        :key="transaction.id"
                        class="flex flex-wrap items-center justify-between gap-2 p-2"
                    >
                        <span>
                            {{ transaction.status_label }}
                            <span
                                v-if="transaction.provider"
                                class="text-muted-foreground text-xs"
                                >• {{ transaction.provider }}</span
                            >
                        </span>
                        <span class="text-muted-foreground text-xs">
                            {{ transaction.amount.formatted }}
                            <span v-if="transaction.processed_at">
                                • {{ formatDate(transaction.processed_at) }}
                            </span>
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
