<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { PlanDetails } from '@/types';

const props = defineProps<{
    plan: PlanDetails;
}>();

const page = usePage();
</script>

<template>
    <Head :title="plan.name" />

    <div class="p-6 max-w-2xl">
        <h1 class="text-3xl font-semibold">{{ plan.name }}</h1>

        <p class="text-muted-foreground mb-6">{{ plan.description }}</p>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <h2 class="font-medium text-sm text-muted-foreground">Price</h2>
                <p class="text-3xl font-medium">{{ plan.price }}</p>

                <div class="mt-2 text-sm text-muted-foreground">
                    <span>Billing interval:</span>
                    <span>{{ plan.interval_label }}</span>
                </div>

                <template v-if="plan.has_trial">
                    <div class="mt-2 text-sm text-muted-foreground">
                        <span>Trial period:</span>
                        <span>{{ plan.trial_days }} days</span>
                    </div>
                </template>
            </div>

            <div>
                <h2 class="font-medium text-sm text-muted-foreground">Features</h2>

                <ul class="mt-4 space-y-2">
                    <li v-for="feature in plan.features" :key="feature.feature">
                        {{ feature.label }}{{
                            feature.unlimited
                                ? ' (unlimited)'
                                : feature.limit !== 0
                                    ? ' limit: ' + feature.limit + ' ' + feature.unit
                                    : ' not included'
                        }}
                    </li>
                </ul>
            </div>
        </div>

        <div class="mt-6 p-4 rounded-lg border" :class="{
            'border-green-200 bg-green-50': plan.is_active,
            'border-gray-200 bg-gray-50': !plan.is_active,
        }">
            <p class="text-sm text-green-800">
                {{ plan.is_active ? 'Active plan - Can be purchased' : 'Inactive plan - Not available for purchase' }}
            </p>
        </div>

        <div class="mt-6">
            <Link href="/organizations/create" class="text-sm text-primary hover:underline">
                Create an organization to get started
            </Link>
        </div>
    </div>
</template>