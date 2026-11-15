<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { FeatureLine, SubscriptionSummary } from '@/types';

const props = defineProps<{
    organization: { id: number; name: string; slug: string };
    subscription: SubscriptionSummary | null;
    features: FeatureLine[];
}>();

function percentage(line: FeatureLine): number {
    if (line.limit === null || line.limit <= 0) {
        return 0;
    }

    return Math.min(100, Math.round((line.used / line.limit) * 100));
}
</script>

<template>
    <Head title="Usage" />

    <div class="p-6">
        <h1 class="text-2xl font-semibold">Usage</h1>
        <p class="text-muted-foreground mt-1 text-sm">
            Feature usage for {{ props.organization.name }} against your current
            plan limits.
        </p>

        <div
            v-if="!props.subscription"
            class="text-muted-foreground mt-6 rounded-lg border p-6 text-sm"
        >
            This organization has no active subscription yet.
            <Link
                :href="`/organizations/${props.organization.slug}/subscription`"
                class="text-foreground ml-1 underline"
            >
                Start a plan
            </Link>
            to see usage.
        </div>

        <div v-else class="mt-6">
            <div class="mb-4 text-sm">
                Plan:
                <span class="font-medium">{{
                    props.subscription.plan?.name ?? '—'
                }}</span>
                <span class="text-muted-foreground">
                    ({{ props.subscription.status_label }})
                </span>
            </div>

            <div
                v-if="props.features.length === 0"
                class="text-muted-foreground rounded-lg border p-6 text-sm"
            >
                No metered features are defined for this plan.
            </div>

            <div v-else class="grid gap-3">
                <div
                    v-for="line in props.features"
                    :key="line.feature"
                    class="rounded-lg border p-4"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-medium">{{ line.label }}</p>
                        <p class="text-sm">
                            <span class="font-medium">{{
                                line.used_label
                            }}</span>
                            <span class="text-muted-foreground">
                                / {{ line.limit_label }}</span
                            >
                        </p>
                    </div>

                    <div
                        class="bg-muted mt-2 h-2 w-full overflow-hidden rounded-full"
                    >
                        <div
                            class="h-full rounded-full"
                            :class="
                                line.exhausted ? 'bg-red-500' : 'bg-primary'
                            "
                            :style="{ width: `${percentage(line)}%` }"
                        />
                    </div>

                    <p class="text-muted-foreground mt-2 text-xs">
                        <span v-if="line.unlimited">Unlimited</span>
                        <span v-else-if="!line.included">Not included</span>
                        <span v-else>{{ line.remaining_label }} remaining</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
