<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import type {
    FeatureLine,
    PlanSummary,
    SubscriptionEventSummary,
    SubscriptionSummary,
} from '@/types';

const props = defineProps<{
    organization: { id: number; name: string; slug: string };
    subscription: SubscriptionSummary | null;
    features: FeatureLine[];
    events: SubscriptionEventSummary[];
    available_plans: PlanSummary[];
    can_start_trial: boolean;
    has_used_trial: boolean;
    permissions: {
        manage: boolean;
        manage_licenses: boolean;
        view_history: boolean;
    };
}>();

const base = `/organizations/${props.organization.slug}`;

const selectedPlan = ref<string>(props.available_plans[0]?.slug ?? '');
const selectedChangePlan = ref<string>(props.available_plans[0]?.slug ?? '');

function startTrial(): void {
    if (selectedPlan.value === '') {
        return;
    }

    router.post(`${base}/subscription/trial`, { plan: selectedPlan.value });
}

function changePlan(): void {
    if (selectedChangePlan.value === '') {
        return;
    }

    router.post(`${base}/subscription/plan`, {
        plan: selectedChangePlan.value,
    });
}

function cancelPlanChange(): void {
    router.delete(`${base}/subscription/plan`);
}

function startCheckout(): void {
    if (selectedPlan.value === '') {
        return;
    }

    router.post(`${base}/checkout`, {
        plan: selectedPlan.value,
        idempotency_key:
            typeof crypto !== 'undefined' && 'randomUUID' in crypto
                ? crypto.randomUUID()
                : null,
    });
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString();
}
</script>

<template>
    <Head title="Subscription" />

    <div class="p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Subscription</h1>
                <p class="text-muted-foreground mt-1 text-sm">
                    Billing for {{ props.organization.name }}.
                </p>
            </div>

            <div class="flex gap-2">
                <Link :href="`/plans`">
                    <Button size="sm" variant="outline">View plans</Button>
                </Link>
                <Link
                    v-if="props.permissions.view_history"
                    :href="`${base}/billing/history`"
                >
                    <Button size="sm" variant="outline">Payment history</Button>
                </Link>
            </div>
        </div>

        <div v-if="!props.subscription" class="mt-6 rounded-lg border p-6">
            <p class="font-medium">No active subscription</p>
            <p class="text-muted-foreground mt-1 text-sm">
                {{
                    props.has_used_trial
                        ? 'This organization has already used its trial. Choose a plan to continue.'
                        : 'Start a free trial or choose a plan to continue.'
                }}
            </p>

            <div
                v-if="props.available_plans.length > 0"
                class="mt-4 grid max-w-sm gap-2"
            >
                <Label for="subscription-plan">Plan</Label>
                <select
                    id="subscription-plan"
                    v-model="selectedPlan"
                    class="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                >
                    <option
                        v-for="plan in props.available_plans"
                        :key="plan.slug"
                        :value="plan.slug"
                    >
                        {{ plan.name }} — {{ plan.price.formatted }}
                    </option>
                </select>

                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="props.can_start_trial && props.permissions.manage"
                        size="sm"
                        @click="startTrial"
                    >
                        Start free trial
                    </Button>
                    <Button
                        v-if="props.permissions.manage"
                        size="sm"
                        variant="outline"
                        @click="startCheckout"
                    >
                        Subscribe now
                    </Button>
                </div>
            </div>
        </div>

        <template v-else>
            <div class="mt-6 rounded-lg border p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-muted-foreground text-xs">
                            Current plan
                        </p>
                        <p class="text-lg font-semibold">
                            {{ props.subscription.plan?.name ?? '—' }}
                        </p>
                        <p class="text-muted-foreground text-sm">
                            {{ props.subscription.price.formatted }} /
                            {{ props.subscription.interval.label }}
                            • {{ props.subscription.status_label }}
                        </p>
                        <p class="text-muted-foreground mt-1 text-xs">
                            Current period:
                            {{
                                formatDate(
                                    props.subscription.current_period_start,
                                )
                            }}
                            –
                            {{
                                formatDate(
                                    props.subscription.current_period_end,
                                )
                            }}
                            <span
                                v-if="
                                    props.subscription.days_remaining !== null
                                "
                            >
                                • {{ props.subscription.days_remaining }} day(s)
                                remaining
                            </span>
                        </p>
                    </div>

                    <div
                        v-if="props.permissions.manage"
                        class="flex flex-wrap gap-2"
                    >
                        <Button
                            v-if="props.subscription.cancel_at_period_end"
                            size="sm"
                            variant="outline"
                            @click="router.post(`${base}/subscription/resume`)"
                        >
                            Resume
                        </Button>
                        <Button
                            v-else-if="!props.subscription.is_cancelled"
                            size="sm"
                            variant="outline"
                            @click="router.post(`${base}/subscription/cancel`)"
                        >
                            Cancel at period end
                        </Button>
                    </div>
                </div>

                <div
                    v-if="props.subscription.pending_plan"
                    class="bg-muted/40 mt-4 rounded-md p-3 text-sm"
                >
                    Scheduled change to
                    <span class="font-medium">{{
                        props.subscription.pending_plan.name
                    }}</span>
                    at the next renewal.
                    <Button
                        v-if="props.permissions.manage"
                        size="sm"
                        variant="outline"
                        class="ml-2"
                        @click="cancelPlanChange"
                    >
                        Cancel change
                    </Button>
                </div>
            </div>

            <div
                v-if="
                    props.permissions.manage_licenses &&
                    props.permissions.manage
                "
                class="mt-4 flex flex-wrap gap-2"
            >
                <Button
                    v-if="!props.subscription.is_suspended"
                    size="sm"
                    variant="outline"
                    @click="router.post(`${base}/subscription/suspend`)"
                >
                    Suspend
                </Button>
                <Button
                    v-else
                    size="sm"
                    variant="outline"
                    @click="router.post(`${base}/subscription/reinstate`)"
                >
                    Reinstate
                </Button>
            </div>

            <div
                v-if="
                    props.permissions.manage && props.available_plans.length > 0
                "
                class="mt-6 rounded-lg border p-4"
            >
                <p class="text-sm font-semibold">Change plan</p>
                <p class="text-muted-foreground mt-1 text-xs">
                    Plan changes are scheduled for the next renewal.
                </p>
                <div class="mt-3 flex flex-wrap items-end gap-2">
                    <div class="grid max-w-xs flex-1 gap-2">
                        <Label for="subscription-change-plan">New plan</Label>
                        <select
                            id="subscription-change-plan"
                            v-model="selectedChangePlan"
                            class="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                        >
                            <option
                                v-for="plan in props.available_plans"
                                :key="plan.slug"
                                :value="plan.slug"
                            >
                                {{ plan.name }} — {{ plan.price.formatted }}
                            </option>
                        </select>
                    </div>
                    <Button size="sm" @click="changePlan"
                        >Schedule change</Button
                    >
                </div>
            </div>

            <div class="mt-6">
                <h2 class="text-lg font-semibold">Features</h2>
                <div
                    v-if="props.features.length === 0"
                    class="text-muted-foreground mt-2 rounded-lg border p-4 text-sm"
                >
                    No features are defined for this plan.
                </div>
                <div v-else class="mt-2 grid gap-2 sm:grid-cols-2">
                    <div
                        v-for="line in props.features"
                        :key="line.feature"
                        class="rounded-lg border p-3 text-sm"
                    >
                        <p class="font-medium">{{ line.label }}</p>
                        <p class="text-muted-foreground text-xs">
                            {{ line.used_label }} / {{ line.limit_label }}
                        </p>
                    </div>
                </div>
            </div>

            <div v-if="props.events.length > 0" class="mt-6">
                <h2 class="text-lg font-semibold">Recent activity</h2>
                <ul class="mt-2 divide-y rounded-lg border">
                    <li
                        v-for="event in props.events"
                        :key="event.id"
                        class="flex items-center justify-between gap-3 p-3 text-sm"
                    >
                        <span>{{ event.type_label }}</span>
                        <span class="text-muted-foreground text-xs">{{
                            formatDate(event.created_at)
                        }}</span>
                    </li>
                </ul>
            </div>
        </template>
    </div>
</template>
