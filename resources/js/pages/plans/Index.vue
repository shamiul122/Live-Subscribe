<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { PlanSummary } from '@/types';

const props = defineProps<{
    plans: PlanSummary[];
}>();

const page = usePage();
</script>

<template>
    <Head title="Plans" />

    <div class="p-6">
        <h1 class="text-2xl font-semibold mb-4">Plans</h1>

        <div v-if="props.plans.length === 0" class="rounded-lg border p-6 text-sm text-muted-foreground">
            No purchasable plans available.
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2">
            <div
                v-for="plan in props.plans"
                :key="plan.id"
                class="rounded-lg border p-4 hover:shadow-sm transition-shadow"
            >
                <h2 class="font-semibold text-lg">{{ plan.name }}</h2>

                <p class="text-sm text-muted-foreground line-clamp-3">
                    {{ plan.description }}
                </p>

                <div class="mt-3 flex flex-col sm:flex-row gap-2 items-start">
                    <div class="flex items-center gap-2">
                        <span class="text-sm">{{ plan.interval_label }}</span>
                        <span class="text-sm font-medium">{{ plan.price }}</span>
                    </div>

                    <template v-if="plan.has_trial">
                        <div class="mt-2 flex items-center gap-2 text-sm text-muted-foreground">
                            <span>Trial: {{ plan.trial_days }} days</span>
                        </div>
                    </template>
                </div>

                <ul class="mt-3 space-y-1 text-sm text-muted-foreground">
                    <li v-for="feature in plan.features" :key="feature.feature">
                        {{ feature.label }}{{ feature.unlimited ? ' (unlimited)' : ' limit: ' + (feature.limit ?? 0) + ' ' + feature.unit }}
                    </li>
                </ul>

                <div v-if="plan.is_purchasable" class="mt-3 p-3 rounded-lg border" :class="{
                    'border-green-200 bg-green-50': plan.is_active,
                    'border-gray-200 bg-gray-50': !plan.is_active,
                }">
                    <p class="text-sm text-green-800">
                        {{ plan.is_active ? 'Active - Purchasable' : 'Inactive - Not purchasable' }}
                    </p>
                </div>

                <Button
                    v-if="plan.is_purchasable"
                    size="sm"
                    variant="default"
                    :href="`/plans/${plan.slug}`"
                >
                    View details
                </Button>

                <Button
                    v-if="!plan.is_purchasable"
                    size="sm"
                    variant="secondary"
                >
                    Not available
                </Button>
            </div>
        </div>
    </div>
</template>